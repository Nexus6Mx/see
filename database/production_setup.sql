-- ============================================================================
-- Sistema de Evidencia ERR (SEE) - Production Setup SQL
-- Version: 1.0
-- Date: 2026-01-14
-- Database: u185421649_see_db (Hostinger Production)
-- Domain: see.errautomotriz.online
-- ============================================================================

-- NOTE: Run this file in your Hostinger PHPMyAdmin
-- The database 'u185421649_see_db' should already exist in your Hostinger account

USE u185421649_see_db;

-- ============================================================================
-- Table: users
-- Purpose: Independent user authentication for SEE system
-- ============================================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('Admin', 'Recepcionista', 'Mecánico') DEFAULT 'Mecánico',
    nombre_completo VARCHAR(255),
    activo BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_activo (activo),
    INDEX idx_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Usuarios del sistema SEE (independientes del sistema principal)';

-- ============================================================================
-- Table: evidencias
-- Purpose: Core evidence records (photos and videos)
-- ============================================================================

CREATE TABLE IF NOT EXISTS evidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_numero VARCHAR(50) NOT NULL COMMENT 'Número de orden del sistema principal',
    archivo_path VARCHAR(500) NOT NULL COMMENT 'Ruta en R2: /YYYY/MM/orden/filename.ext',
    archivo_tipo ENUM('imagen', 'video') NOT NULL,
    archivo_nombre_original VARCHAR(255) COMMENT 'Nombre original del archivo',
    archivo_size_bytes BIGINT COMMENT 'Tamaño del archivo en bytes',
    archivo_mime_type VARCHAR(100),
    thumbnail_path VARCHAR(500) COMMENT 'Ruta de miniatura en R2',
    telegram_file_id VARCHAR(255) COMMENT 'ID de archivo de Telegram',
    telegram_message_id BIGINT COMMENT 'ID del mensaje de Telegram',
    telegram_user_id BIGINT COMMENT 'ID del usuario de Telegram que subió',
    telegram_username VARCHAR(100) COMMENT 'Username de Telegram',
    subido_por_usuario_id INT COMMENT 'ID del usuario SEE si fue upload manual',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activo', 'eliminado') DEFAULT 'activo',
    eliminado_por_usuario_id INT,
    fecha_eliminacion DATETIME,
    
    INDEX idx_orden (orden_numero),
    INDEX idx_fecha (fecha_creacion),
    INDEX idx_estado (estado),
    INDEX idx_tipo (archivo_tipo),
    INDEX idx_telegram_message (telegram_message_id),
    
    FOREIGN KEY (subido_por_usuario_id) 
        REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (eliminado_por_usuario_id) 
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de evidencias multimedia';

-- ============================================================================
-- Table: galeria_tokens
-- Purpose: Secure tokens for customer gallery access (RF-8)
-- ============================================================================

CREATE TABLE IF NOT EXISTS galeria_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_hash VARCHAR(64) UNIQUE NOT NULL COMMENT 'SHA-256 hash del token',
    orden_numero VARCHAR(50) NOT NULL,
    vistas_count INT DEFAULT 0 COMMENT 'Contador de veces que se abrió la galería',
    ultima_vista_ip VARCHAR(45),
    ultima_vista_fecha DATETIME,
    expira_en DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    creado_por_usuario_id INT,
    
    INDEX idx_token_hash (token_hash),
    INDEX idx_orden (orden_numero),
    INDEX idx_expira (expira_en),
    
    FOREIGN KEY (creado_por_usuario_id) 
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tokens seguros para galerías de clientes';

-- ============================================================================
-- Table: audit_logs
-- Purpose: Comprehensive audit trail (RF-9)
-- ============================================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT COMMENT 'Usuario que realizó la acción (null si es sistema)',
    accion ENUM(
        'upload', 'delete', 'view', 'send_notification',
        'login', 'logout', 'create_user', 'update_user',
        'generate_gallery_token', 'api_call'
    ) NOT NULL,
    entidad_tipo VARCHAR(50) COMMENT 'evidencia, notificacion, user, etc.',
    entidad_id INT COMMENT 'ID de la entidad afectada',
    detalles JSON COMMENT 'Información adicional en formato JSON',
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_usuario (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_timestamp (timestamp),
    INDEX idx_entidad (entidad_tipo, entidad_id),
    
    FOREIGN KEY (usuario_id) 
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de auditoría del sistema';

-- ============================================================================
-- Table: notificacion_queue
-- Purpose: Notification retry queue for resilience (RNF-3)
-- ============================================================================

CREATE TABLE IF NOT EXISTS notificacion_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_numero VARCHAR(50) NOT NULL,
    tipo ENUM('whatsapp', 'telegram', 'email') NOT NULL,
    destinatario VARCHAR(255) NOT NULL COMMENT 'Teléfono, email o telegram_id',
    mensaje TEXT,
    galeria_url TEXT,
    estado ENUM('pendiente', 'enviado', 'fallido') DEFAULT 'pendiente',
    intentos INT DEFAULT 0,
    max_intentos INT DEFAULT 3,
    ultimo_error TEXT,
    ultimo_intento_fecha DATETIME,
    enviado_fecha DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    prioridad TINYINT DEFAULT 5 COMMENT '1=alta, 5=normal, 10=baja',
    
    INDEX idx_estado (estado),
    INDEX idx_orden (orden_numero),
    INDEX idx_tipo (tipo),
    INDEX idx_prioridad (prioridad),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cola de notificaciones con reintentos automáticos';

-- ============================================================================
-- Table: cache_client_data
-- Purpose: Cache of client data from main system to reduce bridge API calls
-- ============================================================================

CREATE TABLE IF NOT EXISTS cache_client_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_numero VARCHAR(50) UNIQUE NOT NULL,
    cliente_id INT,
    cliente_nombre VARCHAR(255),
    cliente_telefono VARCHAR(50),
    cliente_email VARCHAR(255),
    vehiculo_modelo VARCHAR(255),
    vehiculo_placas VARCHAR(50),
    fecha_orden DATETIME,
    cached_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    
    INDEX idx_orden (orden_numero),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Caché de datos de clientes del sistema principal';

-- ============================================================================
-- Table: configuracion
-- Purpose: System configuration key-value store
-- ============================================================================

CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    tipo ENUM('string', 'int', 'boolean', 'json') DEFAULT 'string',
    descripcion TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by_user_id INT,
    
    INDEX idx_clave (clave),
    
    FOREIGN KEY (updated_by_user_id) 
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configuración del sistema';

-- ============================================================================
-- Initial Configuration Values
-- ============================================================================

INSERT INTO configuracion (clave, valor, tipo, descripcion) VALUES
('notificacion_auto_enabled', 'true', 'boolean', 'Enviar notificaciones automáticamente al recibir evidencia'),
('galeria_token_expiry_days', '30', 'int', 'Días de validez de tokens de galería'),
('max_file_size_mb', '100', 'int', 'Tamaño máximo de archivo en MB'),
('thumbnail_width', '300', 'int', 'Ancho de miniaturas en pixels'),
('telegram_bot_name', 'SEE_bot', 'string', 'Nombre del bot de Telegram'),
('bridge_api_timeout_seconds', '5', 'int', 'Timeout para llamadas al bridge API'),
('cache_client_data_hours', '24', 'int', 'Horas de validez del caché de datos de clientes'),
('schema_version', '1.0', 'string', 'Versión del esquema de base de datos')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

-- ============================================================================
-- Create Default Admin User
-- Password: admin123 (MUST BE CHANGED IN PRODUCTION)
-- Hash generated with: password_hash('admin123', PASSWORD_BCRYPT)
-- ============================================================================

INSERT INTO users (email, password_hash, rol, nombre_completo, activo) VALUES
('admin@errautomotriz.online', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Administrador del Sistema', TRUE)
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- ============================================================================
-- Views for common queries
-- ============================================================================

-- Active evidence count by order
CREATE OR REPLACE VIEW v_evidencias_por_orden AS
SELECT 
    orden_numero,
    COUNT(*) as total_evidencias,
    SUM(CASE WHEN archivo_tipo = 'imagen' THEN 1 ELSE 0 END) as total_imagenes,
    SUM(CASE WHEN archivo_tipo = 'video' THEN 1 ELSE 0 END) as total_videos,
    SUM(archivo_size_bytes) as total_size_bytes,
    MIN(fecha_creacion) as primera_evidencia,
    MAX(fecha_creacion) as ultima_evidencia
FROM evidencias
WHERE estado = 'activo'
GROUP BY orden_numero;

-- Notification queue summary
CREATE OR REPLACE VIEW v_notificaciones_pendientes AS
SELECT 
    tipo,
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviados,
    SUM(CASE WHEN estado = 'fallido' THEN 1 ELSE 0 END) as fallidos
FROM notificacion_queue
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY tipo;

-- ============================================================================
-- End of Production Setup
-- ============================================================================
