<?php
/**
 * Create temp bridge table and insert test data
 */

require_once __DIR__ . '/../api/config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("❌ Database connection failed\n");
}

echo "🔧 Creating temp bridge table...\n";

// Create table
$createTable = "
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($createTable);
    echo "✅ Table created successfully\n\n";
} catch (PDOException $e) {
    echo "⚠️  Table may already exist: " . $e->getMessage() . "\n\n";
}

// Insert test data
echo "📝 Inserting test client data...\n";

$insert = "
INSERT INTO bridge_clientes_temp 
(orden_numero, cliente_nombre, cliente_email, cliente_telefono, vehiculo_modelo, vehiculo_placas) 
VALUES
('12345', 'Carlos Barba', 'cbarbap@gmail.com', '5577190053', 'Honda Civic EX 2020', 'ERR-TEST')
ON DUPLICATE KEY UPDATE
cliente_email = 'cbarbap@gmail.com',
cliente_telefono = '5577190053'
";

try {
    $db->exec($insert);
    echo "✅ Test data inserted\n\n";
} catch (PDOException $e) {
    echo "❌ Insert failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Verify
echo "🔍 Verifying data:\n";
$stmt = $db->query("SELECT * FROM bridge_clientes_temp");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($data as $row) {
    echo "  Orden: {$row['orden_numero']}\n";
    echo "  Cliente: {$row['cliente_nombre']}\n";
    echo "  Email: {$row['cliente_email']}\n";
    echo "  Teléfono: {$row['cliente_telefono']}\n";
    echo "  Vehículo: {$row['vehiculo_modelo']}\n";
    echo "  Placas: {$row['vehiculo_placas']}\n";
    echo "  ---\n";
}

echo "\n✅ Setup complete! Table ready for testing.\n";
?>