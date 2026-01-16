<?php
/**
 * Debug Script - Evidencias Data
 * Verifica que los datos de evidencias estén correctos
 * 
 * ELIMINAR ESTE ARCHIVO después de debuggear
 */

require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/config/r2_config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();
    $conn = $db->getConnection();

    $debug = [
        'timestamp' => date('Y-m-d H:i:s'),
        'checks' => []
    ];

    // 1. Contar evidencias totales
    $stmt = $conn->query("SELECT COUNT(*) as total FROM evidencias");
    $debug['checks']['total_evidencias'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // 2. Contar evidencias activas
    $stmt = $conn->query("SELECT COUNT(*) as total FROM evidencias WHERE estado = 'activo'");
    $debug['checks']['evidencias_activas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // 3. Muestra de evidencias
    $stmt = $conn->query("
        SELECT 
            id,
            orden_numero,
            archivo_path,
            archivo_nombre_original,
            archivo_tipo,
            archivo_size_bytes,
            thumbnail_path,
            fecha_creacion,
            estado
        FROM evidencias 
        WHERE estado = 'activo' 
        ORDER BY fecha_creacion DESC 
        LIMIT 3
    ");
    $muestras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug['checks']['muestra_evidencias'] = $muestras;

    // 4. Config R2
    $r2Config = require __DIR__ . '/api/config/r2_config.php';
    $debug['checks']['cdn_url'] = $r2Config['cdn_url'];

    // 5. URLs generadas
    if (count($muestras) > 0) {
        $cdnUrl = rtrim($r2Config['cdn_url'], '/');
        $debug['checks']['urls_generadas'] = [];

        foreach ($muestras as $ev) {
            $debug['checks']['urls_generadas'][] = [
                'id' => $ev['id'],
                'orden' => $ev['orden_numero'],
                'archivo_url' => $cdnUrl . '/' . ltrim($ev['archivo_path'], '/'),
                'thumbnail_url' => $ev['thumbnail_path'] ? $cdnUrl . '/' . ltrim($ev['thumbnail_path'], '/') : null
            ];
        }
    }

    // 6. Verificar órdenes únicas
    $stmt = $conn->query("
        SELECT orden_numero, COUNT(*) as total 
        FROM evidencias 
        WHERE estado = 'activo' 
        GROUP BY orden_numero 
        ORDER BY orden_numero
    ");
    $debug['checks']['ordenes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $debug['status'] = 'success';

} catch (Exception $e) {
    $debug['status'] = 'error';
    $debug['error'] = $e->getMessage();
}

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>