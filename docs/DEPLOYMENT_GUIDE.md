# SEE System - Deployment Guide

## 🎯 Prerequisites

Before deploying, ensure you have:

- [ ] Access to Hostinger control panel
- [ ] MySQL/MariaDB access
- [ ] SSH or file transfer access
- [ ] Cloudflare account
- [ ] Telegram Bot token
- [ ] WhatsApp Evolution API credentials (optional)
- [ ] SMTP credentials

---

## 📋 Step-by-Step Deployment

### Step 1: Prepare Hostinger Environment

#### 1.1 Create Database

Via Hostinger panel or SSH:

```bash
mysql -u root -p
```

```sql
CREATE DATABASE db_evidencias CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'see_user'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON db_evidencias.* TO 'see_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 1.2 Import Database Schema

```bash
mysql -u see_user -p db_evidencias < /home/nexus6/devs/see/database/schema.sql
```

✅ **Verify**: Login to database and check tables exist:
```bash
mysql -u see_user -p db_evidencias -e "SHOW TABLES;"
```

---

### Step 2: Upload Files to Hostinger

#### 2.1 Transfer SEE System

Option A - Using SCP:
```bash
scp -r /home/nexus6/devs/see username@your-server:/path/to/see.errautomotriz.online/
```

Option B - Using FTP client (FileZilla, etc.)

#### 2.2 Set Correct Permissions

```bash
ssh username@your-server
cd /path/to/see.errautomotriz.online

# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Make cron executable
chmod +x cron/process_notifications.php

# Protect sensitive files
chmod 600 api/config/*.php
```

---

### Step 3: Install PHP Dependencies

```bash
cd /path/to/see.errautomotriz.online
composer install --no-dev --optimize-autoloader
```

Expected output: AWS SDK, PHPMailer, JWT installed.

---

### Step 4: Configure Application

#### 4.1 Database Configuration

Edit: `api/config/database.php`

```php
private $host = "localhost";
private $db_name = "db_evidencias";
private $username = "see_user";
private $password = "YOUR_SECURE_PASSWORD_HERE";
```

#### 4.2 Cloudflare R2 Configuration

**First, complete Cloudflare R2 setup:**

See detailed guide: `docs/CLOUDFLARE_R2_REQUIREMENTS.md`

Quick steps:
1. Login to Cloudflare Dashboard
2. Go to R2 Object Storage
3. Create bucket: `err-evidencias`
4. Generate API token
5. Configure custom domain: `cdn.errautomotriz.online`

Then edit: `api/config/r2_config.php`

```php
'endpoint' => 'https://YOUR_ACCOUNT_ID.r2.cloudflarestorage.com',
'credentials' => [
    'key' => 'YOUR_ACCESS_KEY_ID',
    'secret' => 'YOUR_SECRET_ACCESS_KEY'
],
'bucket' => 'err-evidencias',
'cdn_url' => 'https://cdn.errautomotriz.online',
```

#### 4.3 Application Configuration

Edit: `api/config/app_config.php`

**Critical settings:**

```php
'base_url' => 'https://see.errautomotriz.online',

'jwt' => [
    'secret' => 'GENERATE_LONG_RANDOM_STRING_HERE',  // Use: openssl rand -base64 32
],

'telegram' => [
    'bot_token' => 'YOUR_TELEGRAM_BOT_TOKEN',
],

'notifications' => [
    'whatsapp' => [
        'api_url' => 'YOUR_EVOLUTION_API_URL',
        'api_key' => 'YOUR_EVOLUTION_API_KEY',
    ],
    'email' => [
        'smtp_host' => 'smtp.hostinger.com',
        'smtp_port' => 587,
        'smtp_user' => 'evidencias@errautomotriz.online',
        'smtp_password' => 'YOUR_EMAIL_PASSWORD',
    ],
],
```

#### 4.4 Bridge API Configuration

Edit: `api/config/bridge_config.php`

```php
'api_url' => 'https://errautomotriz.online/api/bridge/get_client_by_order.php',
'api_key' => 'SEE_BRIDGE_API_KEY_2026',  // Use same key in main system
```

---

### Step 5: Deploy Bridge API to Main System

#### 5.1 Copy File

```bash
cp /home/nexus6/devs/taller-automotriz-app/api/bridge/get_client_by_order.php \
   /path/to/errautomotriz.online/api/bridge/
```

#### 5.2 Verify API Key Matches

In both systems, use the same API key: `SEE_BRIDGE_API_KEY_2026`

#### 5.3 Test Bridge API

```bash
curl -X GET "https://errautomotriz.online/api/bridge/get_client_by_order.php?orden=TEST" \
  -H "X-API-Key: SEE_BRIDGE_API_KEY_2026"
```

Expected: JSON response (404 is OK for test order)

---

### Step 6: Configure Telegram Bot

#### 6.1 Set Webhook

```bash
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
  -d "url=https://see.errautomotriz.online/webhooks/telegram.php"
```

Expected response:
```json
{"ok":true,"result":true,"description":"Webhook was set"}
```

#### 6.2 Verify Webhook

```bash
curl "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getWebhookInfo"
```

Should show your webhook URL.

#### 6.3 Test Bot

Send a test message to your bot. Check logs:

```bash
tail -f /path/to/see.errautomotriz.online/logs/app.log
```

---

### Step 7: Setup Cron Job

#### 7.1 Edit Crontab

```bash
crontab -e
```

#### 7.2 Add Cron Entry

```bash
# SEE Notification Queue Processor - Runs every 5 minutes
*/5 * * * * /usr/bin/php /path/to/see.errautomotriz.online/cron/process_notifications.php >> /path/to/see.errautomotriz.online/logs/cron.log 2>&1
```

#### 7.3 Verify Cron Is Running

Wait 5 minutes, then:
```bash
cat /path/to/see.errautomotriz.online/logs/cron.log
```

---

### Step 8: Create Logs Directory

```bash
cd /path/to/see.errautomotriz.online
mkdir -p logs
chmod 755 logs
touch logs/app.log logs/cron.log
chmod 644 logs/*.log
```

---

### Step 9: Security Hardening

#### 9.1 Change Default Admin Password

Login to database:
```bash
mysql -u see_user -p db_evidencias
```

```sql
UPDATE users 
SET password_hash = '$2y$10$NEW_BCRYPT_HASH_HERE' 
WHERE email = 'admin@errautomotriz.online';
```

Generate new hash in PHP:
```php
echo password_hash('your_new_password', PASSWORD_BCRYPT);
```

#### 9.2 Protect Config Files

Ensure `.gitignore` excludes:
```
api/config/database.php
api/config/*_config.php
.env
logs/
```

#### 9.3 SSL Certificate

Ensure your domain has valid SSL (Hostinger usually provides this).

---

### Step 10: Testing

#### 10.1 Test Database Connection

Create: `test_db.php`
```php
<?php
require_once 'api/config/database.php';
$db = new Database();
if ($db->testConnection()) {
    echo "✅ Database OK\n";
} else {
    echo "❌ Database FAIL\n";
}
```

Run: `php test_db.php`

#### 10.2 Test R2 Connection

Create: `test_r2.php`
```php
<?php
require_once 'vendor/autoload.php';
require_once 'services/R2Service.php';
$r2 = new R2Service();
if ($r2->testConnection()) {
    echo "✅ R2 OK\n";
} else {
    echo "❌ R2 FAIL\n";
}
```

Run: `php test_r2.php`

#### 10.3 Test Bridge API

```bash
curl -X GET "https://errautomotriz.online/api/bridge/get_client_by_order.php?orden=1234" \
  -H "X-API-Key: SEE_BRIDGE_API_KEY_2026"
```

#### 10.4 Test Admin Login

1. Open: `https://see.errautomotriz.online/login.html`
2. Login with default admin (or your new password)
3. Should redirect to dashboard

#### 10.5 Test Telegram Bot

1. Send test photo to bot with "Orden: 12345"
2. Check Telegram for confirmation
3. Check R2 bucket for uploaded file
4. Check database: `SELECT * FROM evidencias;`

#### 10.6 Test Gallery

1. From dashboard, generate gallery link for order
2. Open link in incognito/private window
3. Should display evidences

---

### Step 11: Monitoring

#### 11.1 Setup Log Rotation

Edit `/etc/logrotate.d/see`:
```
/path/to/see.errautomotriz.online/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 0644 www-data www-data
}
```

#### 11.2 Monitor Disk Usage

R2 files won't use server disk (only temps), but monitor:
```bash
du -sh /path/to/see.errautomotriz.online/
```

#### 11.3 Check Logs Regularly

```bash
# Application logs
tail -f /path/to/see.errautomotriz.online/logs/app.log

# Cron logs
tail -f /path/to/see.errautomotriz.online/logs/cron.log

# PHP error logs
tail -f /var/log/php-errors.log
```

---

## 🎉 Post-Deployment

### Create Additional Admin Users

```sql
INSERT INTO users (email, password_hash, rol, nombre_completo, activo)
VALUES (
    'recepcionista@errautomotriz.online',
    '$2y$10$YOUR_BCRYPT_HASH',
    'Recepcionista',
    'Nombre Completo',
    1
);
```

### Train Staff

1. Show login process
2. Demonstrate searching evidences
3. Show how to generate gallery links
4. Demonstrate resending notifications

---

## 🐛 Troubleshooting

### Issue: Telegram webhook not receiving

**Check:**
1. Webhook URL is correct: `getWebhookInfo`
2. SSL certificate is valid
3. File permissions allow execution
4. Check PHP error logs

### Issue: R2 upload fails

**Check:**
1. API credentials are correct
2. Bucket exists and is accessible
3. Firewall allows outbound HTTPS
4. Check R2 service logs

### Issue: Notifications not sending

**Check:**
1. Cron job is running: `crontab -l`
2. Queue has pending items: `SELECT * FROM notificacion_queue;`
3. API credentials (Evolution, SMTP) are correct
4. Check notification logs

### Issue: Bridge API returns errors

**Check:**
1. Main system database is accessible
2. API key matches in both systems
3. Order exists in main database
4. File permissions on bridge endpoint

---

## 📞 Support

For issues:
1. Check logs first
2. Verify configuration files
3. Test individual components
4. Review documentation

---

## ✅ Deployment Checklist

- [ ] Database created and schema imported
- [ ] Files uploaded to server
- [ ] Permissions set correctly
- [ ] Composer dependencies installed
- [ ] Database config updated
- [ ] Cloudflare R2 configured
- [ ] App config updated (JWT, Telegram, SMTP, WhatsApp)
- [ ] Bridge API deployed to main system
- [ ] Bridge config updated
- [ ] Telegram webhook set
- [ ] Cron job configured
- [ ] Logs directory created
- [ ] Default admin password changed
- [ ] All tests passing
- [ ] SSL certificate valid
- [ ] Monitoring setup

**When all checked, system is LIVE! 🚀**
