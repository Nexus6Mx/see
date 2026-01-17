-- ============================================================================
-- Tabla Temporal de Bridge para Testing
-- ============================================================================
-- Esta tabla simula los datos del sistema principal de órdenes
-- Solo para TESTING - será reemplazada por API Bridge en producción
-- ============================================================================

CREATE TABLE IF NOT EXISTS bridge_clientes_temp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_numero VARCHAR(50) NOT NULL UNIQUE,
    cliente_nombre VARCHAR(255) NOT NULL,
    cliente_email VARCHAR(255) NOT NULL,
    cliente_telefono VARCHAR(50),
    vehiculo_modelo VARCHAR(255),
    vehiculo_placas VARCHAR(50),
    vehiculo_color VARCHAR(50),
    fecha_orden DATETIME DEFAULT CURRENT_TIMESTAMP,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_orden (orden_numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Datos de Prueba
-- ============================================================================
-- INSTRUCCIONES: Reemplazar los correos y teléfonos con datos reales del usuario

INSERT INTO bridge_clientes_temp 
(orden_numero, cliente_nombre, cliente_email, cliente_telefono, vehiculo_modelo, vehiculo_placas, vehiculo_color, notas) 
VALUES
-- Orden #12345 - Cliente de prueba 1
('12345', 
 'Juan Carlos Pérez', 
 'TU_EMAIL_1@ejemplo.com',  -- ⚠️ REEMPLAZAR con email real
 '5212221234567',           -- ⚠️ REEMPLAZAR con teléfono real
 'Honda Civic EX 2020', 
 'ABC-1234', 
 'Gris Plata',
 'Cliente frecuente - Servicio de mantenimiento'
),

-- Orden #66666 - Cliente de prueba 2  
('66666', 
 'María Elena González', 
 'TU_EMAIL_2@ejemplo.com',  -- ⚠️ REEMPLAZAR con email real
 '5212229876543',           -- ⚠️ REEMPLAZAR con teléfono real
 'Toyota Corolla LE 2021', 
 'XYZ-7890', 
 'Blanco Perla',
 'Primera visita - Diagnóstico de motor'
),

-- Orden #77777 - Cliente de prueba 3
('77777', 
 'Roberto Sánchez Martínez', 
 'TU_EMAIL_3@ejemplo.com',  -- ⚠️ REEMPLAZAR con email real
 '5212223456789',           -- ⚠️ REEMPLAZAR con teléfono real
 'Mazda 3 Touring 2019', 
 'DEF-4567', 
 'Rojo Metalizado',
 'Reparación de frenos y suspensión'
),

--  Orden #88888 - Cliente de prueba 4
('88888', 
 'Ana Patricia Ramírez', 
 'TU_EMAIL_4@ejemplo.com',  -- ⚠️ REEMPLAZAR con email real
 '5212227654321',           -- ⚠️ REEMPLAZAR con teléfono real
 'Nissan Versa Advance 2022', 
 'GHI-8901', 
 'Azul Marino',
 'Servicio programado 10,000 km'
),

-- Orden #99999 - Cliente de prueba 5
('99999', 
 'Luis Fernando Torres', 
 'TU_EMAIL_5@ejemplo.com',  -- ⚠️ REEMPLAZAR con email real
 '5212228765432',           -- ⚠️ REEMPLAZAR con teléfono real
 'Volkswagen Jetta GLI 2020', 
 'JKL-2345', 
 'Negro Profundo',
 'Cambio de aceite y revisión general'
);

-- ============================================================================
-- Verificación
-- ============================================================================
SELECT 
    orden_numero,
    cliente_nombre,
    cliente_email,
    vehiculo_modelo
FROM bridge_clientes_temp
ORDER BY orden_numero;

-- Expected output:
-- +---------------+-------------------------+----------------------+------------------------+
-- | orden_numero  | cliente_nombre          | cliente_email        | vehiculo_modelo        |
-- +---------------+-------------------------+----------------------+------------------------+
-- | 12345         | Juan Carlos Pérez       | TU_EMAIL_1@...       | Honda Civic EX 2020    |
-- | 66666         | María Elena González    | TU_EMAIL_2@...       | Toyota Corolla LE 2021 |
-- | 77777         | Roberto Sánchez M.      | TU_EMAIL_3@...       | Mazda 3 Touring 2019   |
-- | 88888         | Ana Patricia Ramírez    | TU_EMAIL_4@...       | Nissan Versa Adv 2022  |
-- | 99999         | Luis Fernando Torres    | TU_EMAIL_5@...       | VW Jetta GLI 2020      |
-- +---------------+-------------------------+----------------------+------------------------+

-- ============================================================================
-- NOTAS:
-- - Esta tabla es TEMPORAL para testing
-- - En producción se usará el API Bridge del sistema principal
-- - Puedes agregar más órdenes con: INSERT INTO bridge_clientes_temp VALUES (...)
-- - Para eliminar: DROP TABLE bridge_clientes_temp;
-- ============================================================================
