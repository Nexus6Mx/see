# Sistema de Evidencia ERR (SEE)

Sistema independiente para la gestión de evidencias fotográficas y de video de servicios automotrices.

## 🚀 Características

- **Ingesta Multimodal**: Recepción de fotos y videos vía Telegram Bot
- **Almacenamiento en la Nube**: Cloudflare R2 (S3-compatible)
- **Galerías Seguras**: Enlaces únicos con expiración para clientes
- **Notificaciones Omnicanal**: WhatsApp, Telegram y Email
- **Dashboard Administrativo**: Gestión completa de evidencias
- **Independencia Total**: Base de datos separada del sistema principal

## 📋 Requisitos

- PHP 8.0 o superior
- MariaDB/MySQL 10.5+
- Composer
- Cuenta de Cloudflare R2
- Bot de Telegram
- SSL/HTTPS configurado

## 🔧 Instalación

### 1. Clonar/Descargar el Proyecto

```bash
cd /home/nexus6/devs/see
```

### 2. Instalar Dependencias

```bash
composer install
```

### 3. Configurar Base de Datos

```bash
# Crear base de datos
mysql -u root -p -e "CREATE DATABASE db_evidencias CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Crear usuario
mysql -u root -p -e "CREATE USER 'see_user'@'localhost' IDENTIFIED BY 'your_secure_password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON db_evidencias.* TO 'see_user'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"

# Importar esquema
mysql -u see_user -p db_evidencias < database/schema.sql
```

### 4. Configurar Archivos

```bash
# Copiar ejemplos de configuración
cp api/config/database.example.php api/config/database.php
# Editar y actualizar credenciales
nano api/config/database.php

# Actualizar credenciales de R2 (después de configurar Cloudflare)
nano api/config/r2_config.php

# Actualizar configuración del bridge API
nano api/config/bridge_config.php
```

### 5. Configurar Cloudflare R2

Ver guía detallada: [`docs/CLOUDFLARE_R2_REQUIREMENTS.md`](docs/CLOUDFLARE_R2_REQUIREMENTS.md)

### 6. Configurar Bot de Telegram

```bash
# Establecer webhook
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
  -d "url=https://see.errautomotriz.online/webhooks/telegram.php"
```

### 7. Configurar Cron Job

```bash
# Agregar a crontab
crontab -e

# Procesar cola de notificaciones cada 5 minutos
*/5 * * * * /usr/bin/php /path/to/see/cron/process_notifications.php
```

## 🏗️ Estructura del Proyecto

```
see/
├── api/                      # Backend API
│   ├── config/              # Configuración
│   ├── auth/                # Autenticación
│   ├── evidencias/          # CRUD de evidencias
│   ├── galeria/             # Gestión de galerías
│   └── audit/               # Logs de auditoría
├── services/                # Servicios de negocio
│   ├── R2Service.php
│   ├── BridgeService.php
│   ├── NotificationService.php
│   └── ThumbnailService.php
├── webhooks/                # Webhooks externos
│   └── telegram.php
├── public/                  # Frontend público
│   ├── index.html
│   ├── login.html
│   ├── dashboard.html
│   └── galeria.php
├── assets/                  # CSS/JS
├── database/                # SQL schemas
├── docs/                    # Documentación
├── tests/                   # Tests
└── composer.json
```

## 📖 Documentación

- [Documentación Técnica Completa](docs/TECHNICAL_DOCUMENTATION.md)
- [Requerimientos de Cloudflare R2](docs/CLOUDFLARE_R2_REQUIREMENTS.md)
- [Documentación del Bridge API](docs/BRIDGE_API_DOCUMENTATION.md)
- [Plan de Implementación](../../../.gemini/antigravity/brain/7c3946a1-a50e-4fa0-b3c9-b19b295536f3/implementation_plan.md)

## 🔒 Seguridad

- Autenticación JWT para administradores
- Tokens SHA-256 para galerías de clientes
- API key para comunicación con sistema principal
- Validación estricta de tipos de archivos
- Logs de auditoría completos
- HTTPS obligatorio en producción

## 🧪 Testing

```bash
# Unit tests
composer test

# Test de conexión a base de datos
php tests/test_database.php

# Test de bridge API
php tests/test_bridge.php
```

## 📱 Uso del Sistema

### Para Mecánicos (Upload de Evidencias)

1. Tomar foto/video del servicio
2. Enviar al bot de Telegram con el caption: `Orden: 12345`
3. El bot confirmará la recepción

### Para Administradores

1. Acceder a `https://see.errautomotriz.online/login.html`
2. Dashboard muestra todas las evidencias
3. Generar enlace de galería para el cliente
4. Enviar notificación (automática o manual)

### Para Clientes

1. Reciben enlace único por WhatsApp/Email/Telegram
2. Acceden a galería web optimizada para móviles
3. Ven fotos y videos del servicio de su vehículo

## 🔧 Mantenimiento

### Limpiar Tokens Expirados

```sql
DELETE FROM galeria_tokens WHERE expira_en < NOW();
```

### Limpiar Caché Antiguo

```sql
DELETE FROM cache_client_data WHERE expires_at < NOW();
```

### Backup de Base de Datos

```bash
mysqldump -u see_user -p db_evidencias > backup_$(date +%Y%m%d).sql
```

## 🐛 Troubleshooting

### Error: "Database connection failed"

- Verificar credenciales en `api/config/database.php`
- Confirmar que la base de datos existe
- Revisar permisos del usuario

### Error: "R2 upload failed"

- Verificar credenciales en `api/config/r2_config.php`
- Confirmar que el bucket existe
- Revisar configuración CORS

### Notificaciones no se envían

- Revisar cola: `SELECT * FROM notificacion_queue WHERE estado = 'fallido';`
- Verificar configuración de Evolution API / SMTP
- Revisar logs: `tail -f logs/app.log`

## 📞 Soporte

- **Repositorio**: `/home/nexus6/devs/see`
- **Documentación**: `/docs`
- **Logs**: `/logs/app.log`

## 📜 Licencia

Uso interno - ERR Automotriz

## 🗺️ Roadmap

- [ ] App móvil para mecánicos
- [ ] Compresión automática de videos
- [ ] OCR para detección automática de número de orden
- [ ] Analytics y reportes
- [ ] Multi-idioma

---

**Versión**: 1.0.0  
**Última actualización**: 2026-01-09  
**Desarrollado por**: Nexus6 Consulting
