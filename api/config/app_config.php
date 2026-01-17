<?php
/**
 * Application Configuration
 * General SEE system settings
 */

return [
    // Application info
    'app_name' => 'Sistema de Evidencia ERR',
    'app_version' => '1.0.0',
    'app_env' => getenv('APP_ENV') ?: 'production',  // development, staging, production

    // Base URLs
    'base_url' => getenv('BASE_URL') ?: 'https://see.errautomotriz.online',
    'api_base' => '/api',

    // JWT Configuration
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: 'CHANGE_THIS_IN_PRODUCTION_TO_RANDOM_STRING',
        'algorithm' => 'HS256',
        'expiration' => 86400,  // 24 hours in seconds
        'issuer' => 'SEE-System',
        'audience' => 'SEE-Users'
    ],

    // Session configuration
    'session' => [
        'name' => 'SEE_SESSION',
        'lifetime' => 86400,  // 24 hours
        'secure' => true,  // Require HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ],

    // Telegram Bot
    'telegram' => [
        'bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '8183422633:AAGP2H90KsX05bEWNeYsMBzGpOEbEiWZsII',
        'webhook_secret' => getenv('TELEGRAM_WEBHOOK_SECRET') ?: 'RANDOM_SECRET_FOR_WEBHOOK',
        'api_url' => 'https://api.telegram.org/bot',
        'allowed_file_types' => ['photo', 'video', 'document'],
        'max_file_size' => 100 * 1024 * 1024  // 100 MB
    ],

    // Notification settings
    'notifications' => [
        'auto_send' => true,  // Automatically send notifications on evidence upload
        'default_channel' => 'whatsapp',  // whatsapp, telegram, email
        'retry_failed' => true,
        'max_retries' => 3,
        'retry_delays' => [300, 1800, 7200],  // 5min, 30min, 2h in seconds

        // WhatsApp (Evolution API)
        'whatsapp' => [
            'enabled' => false,  // Disabled until Evolution API is configured
            'api_url' => getenv('EVOLUTION_API_URL') ?: 'https://your-evolution-api.com',
            'api_key' => getenv('EVOLUTION_API_KEY') ?: 'YOUR_EVOLUTION_API_KEY',
            'instance_name' => getenv('EVOLUTION_INSTANCE') ?: 'err_instance'
        ],

        // Email (SMTP)
        'email' => [
            'enabled' => true,
            'from_address' => 'evidencias@see.errautomotriz.online',
            'from_name' => 'ERR Automotriz - Evidencias',
            'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.hostinger.com',
            'smtp_port' => getenv('SMTP_PORT') ?: 465,
            'smtp_user' => getenv('SMTP_USER') ?: '',
            'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
            'smtp_secure' => 'ssl'  // ssl for port 465, tls for port 587
        ],

        // Templates
        'templates' => [
            'whatsapp' => "¡Hola {cliente_nombre}!\n\nLas evidencias del servicio de su vehículo *{vehiculo_modelo}* (Orden #{orden_numero}) están listas.\n\nPuede verlas aquí: {galeria_url}\n\nEste enlace estará disponible por 30 días.\n\nGracias por confiar en nosotros.\n*ERR Automotriz*",

            'email_subject' => 'Evidencias de su servicio - Orden #{orden_numero}',

            'email_body' => '<p>Estimado/a {cliente_nombre},</p><p>Las evidencias fotográficas y de video del servicio realizado a su vehículo <strong>{vehiculo_modelo}</strong> (Orden #{orden_numero}) están disponibles.</p><p><a href="{galeria_url}" style="background-color:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;">Ver Evidencias</a></p><p>Este enlace estará disponible por 30 días.</p><p>Gracias por confiar en ERR Automotriz.</p>'
        ]
    ],

    // Gallery settings
    'gallery' => [
        'token_expiry_days' => 30,
        'max_views' => null,  // null = unlimited
        'items_per_page' => 50,
        'lazy_load' => true,
        'video_player' => 'html5'  // html5, plyr
    ],

    // File processing
    'files' => [
        'temp_dir' => sys_get_temp_dir() . '/see_uploads',
        'clean_temp_after_upload' => true,
        'generate_thumbnails' => true,
        'thumbnail_quality' => 80
    ],

    // Logging
    'logging' => [
        'enabled' => true,
        'level' => getenv('LOG_LEVEL') ?: 'info',  // debug, info, warning, error
        'file' => __DIR__ . '/../../logs/app.log',
        'max_size_mb' => 50,
        'rotate_days' => 30
    ],

    // Security
    'security' => [
        'enable_cors' => true,
        'allowed_origins' => [
            'https://see.errautomotriz.online',
            'https://errautomotriz.online'
        ],
        'rate_limiting' => [
            'enabled' => true,
            'max_requests_per_minute' => 60
        ],
        'csrf_protection' => true,
        'xss_protection' => true
    ],

    // Cache
    'cache' => [
        'enabled' => true,
        'driver' => 'database',  // database, file, redis
        'ttl_default' => 3600  // 1 hour
    ],

    // Pagination
    'pagination' => [
        'items_per_page' => 20,
        'max_items_per_page' => 100
    ],

    // Audit
    'audit' => [
        'enabled' => true,
        'log_all_api_calls' => false,  // Can generate a lot of logs
        'log_sensitive_actions' => true,  // delete, send_notification, etc.
        'retention_days' => 365
    ]
];
