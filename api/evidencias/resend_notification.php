<?php
/**
 * Resend Notification API
 * Manually resend notifications for an order
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/JWTHelper.php';
require_once __DIR__ . '/../../services/NotificationService.php';
require_once __DIR__ . '/../../services/BridgeService.php';

header('Content-Type: application/json');

// Require authentication (Admin or Recepcionista)
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
$canal = $data['canal'] ?? 'whatsapp'; // whatsapp, email, telegram

if (empty($ordenNumero)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Número de orden requerido'
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
            'error' => 'No se encontraron evidencias para esta orden'
        ]);
        exit;
    }

    // Get or generate gallery token
    $tokenQuery = "SELECT * FROM galeria_tokens WHERE orden_numero = :orden_numero AND expira_en > NOW() ORDER BY created_at DESC LIMIT 1";
    $tokenStmt = $db->prepare($tokenQuery);
    $tokenStmt->bindParam(':orden_numero', $ordenNumero);
    $tokenStmt->execute();
    $tokenData = $tokenStmt->fetch(PDO::FETCH_ASSOC);

    $galeriaUrl = null;

    if ($tokenData) {
        // Use existing token - need to regenerate plain token (we only have hash)
        // For security, generate new token
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $updateQuery = "UPDATE galeria_tokens SET token_hash = :token_hash, created_at = NOW() WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([':token_hash' => $tokenHash, ':id' => $tokenData['id']]);
    } else {
        // Generate new token
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $insertQuery = "
            INSERT INTO galeria_tokens (token_hash, orden_numero, expira_en, creado_por_usuario_id)
            VALUES (:token_hash, :orden_numero, :expira_en, :creado_por)
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

    // Get client data
    $bridgeService = new BridgeService($db);
    $clientData = $bridgeService->getClientByOrder($ordenNumero);

    if (!$clientData) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'No se encontraron datos del cliente para esta orden'
        ]);
        exit;
    }

    // Generate message
    $notificationService = new NotificationService($db);

    $templateData = [
        'cliente_nombre' => $clientData['cliente_nombre'] ?? 'Cliente',
        'vehiculo_modelo' => $clientData['vehiculo_modelo'] ?? 'su vehículo',
        'orden_numero' => $ordenNumero,
        'galeria_url' => $galeriaUrl
    ];

    // Determine recipient
    $destinatario = '';
    switch ($canal) {
        case 'whatsapp':
            $destinatario = $clientData['cliente_telefono'] ?? '';
            if (empty($destinatario)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cliente no tiene teléfono registrado']);
                exit;
            }
            $mensaje = $notificationService->generateMessage('whatsapp', $templateData);
            break;

        case 'email':
            $destinatario = $clientData['cliente_email'] ?? '';
            if (empty($destinatario)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cliente no tiene email registrado']);
                exit;
            }
            $emailData = $notificationService->generateMessage('email', $templateData);
            $mensaje = $emailData['body'];
            break;

        case 'telegram':
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Canal Telegram requiere chat_id (no implementado aún)']);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Canal no válido']);
            exit;
    }

    // Try to send immediately
    $extra = ['gallery_url' => $galeriaUrl];
    if ($canal === 'email') {
        $extra['subject'] = $emailData['subject'];
        $extra['html_body'] = $emailData['body'];
    }

    $result = $notificationService->send($canal, $destinatario, $mensaje, $extra);

    if ($result['success']) {
        // Log action
        $logQuery = "
            INSERT INTO audit_logs (usuario_id, accion, entidad_tipo, entidad_id, detalles, ip_address, timestamp)
            VALUES (:usuario_id, 'send_notification', 'notificacion', 0, :detalles, :ip_address, NOW())
        ";
        $logStmt = $db->prepare($logQuery);
        $logStmt->execute([
            ':usuario_id' => $currentUser['user_id'],
            ':detalles' => json_encode(['orden_numero' => $ordenNumero, 'canal' => $canal]),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Notificación enviada exitosamente',
            'canal' => $canal,
            'destinatario' => $destinatario
        ]);
    } else {
        // Failed - queue for retry
        $notificationService->queue($ordenNumero, $canal, $destinatario, $mensaje, $galeriaUrl, 1); // High priority

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo enviar la notificación',
            'details' => $result['error'],
            'queued' => true,
            'message' => 'La notificación fue encolada para reintento automático'
        ]);
    }

} catch (Exception $e) {
    error_log("[Resend Notification API] Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?>