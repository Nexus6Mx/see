<?php
/**
 * Generate Gallery Token API
 * Creates secure gallery access token for customers
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/JWTHelper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Require authentication (admin or recepcionista only)
$currentUser = JWTHelper::requireAuth(['Admin', 'Recepcionista']);

// Get database connection
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$ordenNumero = $data['orden_numero'] ?? '';
$expiraDias = $data['expira_dias'] ?? 30;

if (empty($ordenNumero)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Número de orden requerido',
        'code' => 'MISSING_ORDER_NUMBER'
    ]);
    exit;
}

try {
    // Check if order has evidences
    $checkQuery = "SELECT COUNT(*) as count FROM evidencias WHERE orden_numero = :orden_numero AND estado = 'activo'";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':orden_numero', $ordenNumero);
    $checkStmt->execute();
    $evidenciasCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($evidenciasCount == 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'No se encontraron evidencias para esta orden',
            'code' => 'NO_EVIDENCES_FOUND'
        ]);
        exit;
    }

    // Generate cryptographically secure token
    $token = bin2hex(random_bytes(32));  // 64 character hex string
    $tokenHash = hash('sha256', $token);

    // Calculate expiration
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiraDias} days"));

    // Check if token already exists for this order
    $existingQuery = "SELECT id FROM galeria_tokens WHERE orden_numero = :orden_numero AND expira_en > NOW() LIMIT 1";
    $existingStmt = $db->prepare($existingQuery);
    $existingStmt->bindParam(':orden_numero', $ordenNumero);
    $existingStmt->execute();
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing token
        $updateQuery = "
            UPDATE galeria_tokens 
            SET token_hash = :token_hash, 
                expira_en = :expira_en, 
                created_at = NOW(),
                creado_por_usuario_id = :creado_por
            WHERE id = :id
        ";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([
            ':token_hash' => $tokenHash,
            ':expira_en' => $expiresAt,
            ':creado_por' => $currentUser['user_id'],
            ':id' => $existing['id']
        ]);
    } else {
        // Insert new token
        $insertQuery = "
            INSERT INTO galeria_tokens (
                token_hash, orden_numero, expira_en, creado_por_usuario_id
            ) VALUES (
                :token_hash, :orden_numero, :expira_en, :creado_por
            )
        ";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->execute([
            ':token_hash' => $tokenHash,
            ':orden_numero' => $ordenNumero,
            ':expira_en' => $expiresAt,
            ':creado_por' => $currentUser['user_id']
        ]);
    }

    // Generate gallery URL
    $appConfig = require __DIR__ . '/../config/app_config.php';
    $baseUrl = rtrim($appConfig['base_url'], '/');
    $galeriaUrl = "{$baseUrl}/galeria.php?t={$token}";

    // Log action
    $logQuery = "
        INSERT INTO audit_logs (
            usuario_id, accion, entidad_tipo, entidad_id, detalles, 
            ip_address, timestamp
        ) VALUES (
            :usuario_id, 'generate_gallery_token', 'galeria_token', 0,
            :detalles, :ip_address, NOW()
        )
    ";
    $logStmt = $db->prepare($logQuery);
    $logStmt->execute([
        ':usuario_id' => $currentUser['user_id'],
        ':detalles' => json_encode(['orden_numero' => $ordenNumero]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'url' => $galeriaUrl,
            'token' => $token,
            'orden_numero' => $ordenNumero,
            'evidencias_count' => (int) $evidenciasCount,
            'expira_en' => $expiresAt,
            'expira_dias' => $expiraDias
        ]
    ]);

} catch (Exception $e) {
    error_log("[Generate Token API] Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?>