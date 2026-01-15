#!/bin/bash
# SEE System - Install Telegram Webhook
# This script configures the Telegram webhook for production

BOT_TOKEN="8183422633:AAGP2H90KsX05bEWNeYsMBzGpOEbEiWZsII"
WEBHOOK_URL="https://see.errautomotriz.online/webhooks/telegram.php"

echo "Setting up Telegram webhook for SEE_bot..."
echo "Webhook URL: $WEBHOOK_URL"

# Set webhook
response=$(curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
  -d "url=${WEBHOOK_URL}" \
  -d "max_connections=40" \
  -d "allowed_updates=[\"message\",\"edited_message\"]")

echo "Response: $response"

# Verify webhook
echo -e "\nVerifying webhook configuration..."
curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo" | python3 -m json.tool

echo -e "\n✓ Webhook setup complete!"
echo "Note: Make sure the webhook endpoint is accessible and returns 200 OK"
