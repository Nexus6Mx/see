-- Tabla Temporal de Bridge - Cliente de Prueba
CREATE TABLE IF NOT EXISTS bridge_clientes_temp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_numero VARCHAR(50) NOT NULL UNIQUE,
    cliente_nombre VARCHAR(255) NOT NULL,
    cliente_email VARCHAR(255) NOT NULL,
    cliente_telefono VARCHAR(50),
    vehiculo_modelo VARCHAR(255),
    vehiculo_placas VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_orden (orden_numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cliente de Prueba
INSERT INTO bridge_clientes_temp 
(orden_numero, cliente_nombre, cliente_email, cliente_telefono, vehiculo_modelo, vehiculo_placas) 
VALUES
('12345', 
 'Carlos Barba', 
 'cbarbap@gmail.com',
 '5577190053',
 'Honda Civic EX 2020', 
 'ERR-TEST'
);

-- Verificar
SELECT * FROM bridge_clientes_temp;
