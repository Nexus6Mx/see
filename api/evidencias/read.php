<?php
/**
 * Evidencias Read API
 * List all evidences with filters and pagination
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/JWTHelper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Require authentication
$currentUser = JWTHelper::requireAuth();

// Get database connection
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    // Get query parameters
    $ordenNumero = $_GET['orden'] ?? null;
    $fechaInicio = $_GET['fecha_inicio'] ?? null;
    $fechaFin = $_GET['fecha_fin'] ?? null;
    $tipo = $_GET['tipo'] ?? null;
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? min((int) $_GET['limit'], 100) : 20;
    $offset = ($page - 1) * $limit;

    // Build query
    $conditions = ["estado = 'activo'"];
    $params = [];

    if ($ordenNumero) {
        $conditions[] = "orden_numero = :orden_numero";
        $params[':orden_numero'] = $ordenNumero;
    }

    if ($fechaInicio) {
        $conditions[] = "fecha_creacion >= :fecha_inicio";
        $params[':fecha_inicio'] = $fechaInicio . ' 00:00:00';
    }

    if ($fechaFin) {
        $conditions[] = "fecha_creacion <= :fecha_fin";
        $params[':fecha_fin'] = $fechaFin . ' 23:59:59';
    }

    if ($tipo && in_array($tipo, ['imagen', 'video'])) {
        $conditions[] = "archivo_tipo = :tipo";
        $params[':tipo'] = $tipo;
    }

    $whereClause = implode(' AND ', $conditions);

    // Count total records
    $countQuery = "SELECT COUNT(*) as total FROM evidencias WHERE {$whereClause}";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get evidences
    $query = "
        SELECT 
            e.*,
            u.email as subido_por_email
        FROM evidencias e
        LEFT JOIN users u ON e.subido_por_usuario_id = u.id
        WHERE {$whereClause}
        ORDER BY e.fecha_creacion DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    $evidencias = [];
    $r2Config = require __DIR__ . '/../config/r2_config.php';
    $cdnUrl = rtrim($r2Config['cdn_url'], '/');

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $evidencias[] = [
            'id' => (int) $row['id'],
            'orden_numero' => $row['orden_numero'],
            'archivo_path' => $row['archivo_path'],
            'archivo_url' => $cdnUrl . '/' . ltrim($row['archivo_path'], '/'),
            'archivo_tipo' => $row['archivo_tipo'],
            'archivo_nombre_original' => $row['archivo_nombre_original'],
            'archivo_size_bytes' => (int) $row['archivo_size_bytes'],
            'archivo_size_formatted' => formatBytes($row['archivo_size_bytes']),
            'thumbnail_path' => $row['thumbnail_path'],
            'thumbnail_url' => $row['thumbnail_path'] ? $cdnUrl . '/' . ltrim($row['thumbnail_path'], '/') : null,
            'telegram_username' => $row['telegram_username'],
            'subido_por_email' => $row['subido_por_email'],
            'fecha_creacion' => $row['fecha_creacion']
        ];
    }

    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $evidencias,
        'pagination' => [
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {
    error_log("[Evidencias Read API] Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}

function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>