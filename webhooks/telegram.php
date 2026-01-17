<?php
/**
 * Telegram Webhook Handler
 * Receives and processes messages from Telegram Bot
 * Handles file uploads, order number extraction, and evidence storage
 */

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/config/app_config.php';
require_once __DIR__ . '/../services/R2Service.php';
require_once __DIR__ . '/../services/ThumbnailService.php';
require_once __DIR__ . '/../services/BridgeService.php';

// Set headers
header('Content-Type: application/json');

// Read Telegram webhook payload
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// Log incoming webhook
error_log("[Telegram Webhook] Received update: " . substr($input, 0, 500));

// Validate JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("[Telegram Webhook] Invalid JSON payload");
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Load config
$appConfig = require __DIR__ . '/../api/config/app_config.php';
$telegramConfig = $appConfig['telegram'];
$botToken = $telegramConfig['bot_token'];

// Initialize database
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    error_log("[Telegram Webhook] Database connection failed");
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal error']);
    exit;
}

try {
    // Extract message data
    $message = $update['message'] ?? null;

    if (!$message) {
        // Not a message update (could be edited_message, callback_query, etc.)
        echo json_encode(['ok' => true, 'status' => 'ignored']);
        exit;
    }

    $chatId = $message['chat']['id'];
    $messageId = $message['message_id'];
    $userId = $message['from']['id'];
    $username = $message['from']['username'] ?? $message['from']['first_name'] ?? 'Unknown';

    // Extract caption or text
    $caption = $message['caption'] ?? $message['text'] ?? '';

    // Extract order number from caption/text
    $orderNumber = extractOrderNumber($caption);

    // Check if message contains supported files
    $file = null;
    $fileType = null;

    if (isset($message['photo'])) {
        // Get largest photo
        $photo = end($message['photo']);
        $file = [
            'file_id' => $photo['file_id'],
            'file_size' => $photo['file_size'] ?? 0
        ];
        $fileType = 'imagen';
    } elseif (isset($message['video'])) {
        $file = [
            'file_id' => $message['video']['file_id'],
            'file_size' => $message['video']['file_size'] ?? 0
        ];
        $fileType = 'video';
    } elseif (isset($message['document'])) {
        // Check if document is an image or video
        $mimeType = $message['document']['mime_type'] ?? '';
        if (strpos($mimeType, 'image/') === 0) {
            $file = [
                'file_id' => $message['document']['file_id'],
                'file_size' => $message['document']['file_size'] ?? 0
            ];
            $fileType = 'imagen';
        } elseif (strpos($mimeType, 'video/') === 0) {
            $file = [
                'file_id' => $message['document']['file_id'],
                'file_size' => $message['document']['file_size'] ?? 0
            ];
            $fileType = 'video';
        }
    }

    // If no file found, respond with instructions
    if (!$file) {
        sendTelegramMessage(
            $chatId,
            "📸 Por favor envía una foto o video con el número de orden en el caption.\n\nEjemplo: `Orden: 12345`",
            $botToken
        );
        echo json_encode(['ok' => true, 'status' => 'no_file']);
        exit;
    }

    // If no order number found, ask for it
    if (!$orderNumber) {
        sendTelegramMessage(
            $chatId,
            "❌ No se encontró el número de orden.\n\nPor favor incluye el número de orden en el mensaje.\nEjemplo: `Orden: 12345` o `#12345`",
            $botToken
        );
        echo json_encode(['ok' => true, 'status' => 'no_order_number']);
        exit;
    }

    // Validate file size
    $maxFileSize = $telegramConfig['max_file_size'];
    if ($file['file_size'] > $maxFileSize) {
        $maxFileSizeMB = round($maxFileSize / 1024 / 1024);
        sendTelegramMessage(
            $chatId,
            "❌ El archivo es demasiado grande.\n\nTamaño máximo permitido: {$maxFileSizeMB} MB",
            $botToken
        );
        echo json_encode(['ok' => true, 'status' => 'file_too_large']);
        exit;
    }

    // Download file from Telegram
    $downloadedFile = downloadTelegramFile($file['file_id'], $botToken);

    if (!$downloadedFile) {
        sendTelegramMessage(
            $chatId,
            "❌ Error al descargar el archivo. Por favor intenta de nuevo.",
            $botToken
        );
        echo json_encode(['ok' => false, 'error' => 'Download failed']);
        exit;
    }

    // Validate MIME type
    $mimeType = mime_content_type($downloadedFile['path']);
    $r2Config = require __DIR__ . '/../api/config/r2_config.php';
    $allowedTypes = array_merge(
        $r2Config['upload']['allowed_image_types'],
        $r2Config['upload']['allowed_video_types']
    );

    if (!in_array($mimeType, $allowedTypes)) {
        unlink($downloadedFile['path']);
        sendTelegramMessage(
            $chatId,
            "❌ Tipo de archivo no permitido: {$mimeType}\n\nSolo se permiten JPG, PNG y MP4.",
            $botToken
        );
        echo json_encode(['ok' => true, 'status' => 'invalid_mime_type']);
        exit;
    }

    // Initialize services
    $r2Service = new R2Service();
    $thumbnailService = new ThumbnailService();

    // Upload to R2
    $uploadResult = $r2Service->upload(
        $downloadedFile['path'],
        $orderNumber,
        $downloadedFile['filename'],
        $fileType
    );

    // Generate thumbnail
    $thumbnailPath = $thumbnailService->generate($downloadedFile['path'], $mimeType);
    $thumbnailResult = null;

    if ($thumbnailPath && file_exists($thumbnailPath)) {
        $thumbnailResult = $r2Service->uploadThumbnail($thumbnailPath, $uploadResult['path']);
        // Clean up local thumbnail
        unlink($thumbnailPath);
    }

    // Clean up downloaded file
    unlink($downloadedFile['path']);

    // Save to database
    $query = "
        INSERT INTO evidencias (
            orden_numero, archivo_path, archivo_tipo, archivo_nombre_original,
            archivo_size_bytes, archivo_mime_type, thumbnail_path,
            telegram_file_id, telegram_message_id, telegram_user_id, telegram_username,
            fecha_creacion
        ) VALUES (
            :orden_numero, :archivo_path, :archivo_tipo, :archivo_nombre_original,
            :archivo_size_bytes, :archivo_mime_type, :thumbnail_path,
            :telegram_file_id, :telegram_message_id, :telegram_user_id, :telegram_username,
            NOW()
        )
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([
        ':orden_numero' => $orderNumber,
        ':archivo_path' => $uploadResult['path'],
        ':archivo_tipo' => $fileType,
        ':archivo_nombre_original' => $downloadedFile['filename'],
        ':archivo_size_bytes' => $uploadResult['size'],
        ':archivo_mime_type' => $uploadResult['mime_type'],
        ':thumbnail_path' => $thumbnailResult ? $thumbnailResult['path'] : null,
        ':telegram_file_id' => $file['file_id'],
        ':telegram_message_id' => $messageId,
        ':telegram_user_id' => $userId,
        ':telegram_username' => $username
    ]);

    $evidenciaId = $db->lastInsertId();

    // Log audit
    logAudit($db, null, 'upload', 'evidencia', $evidenciaId, [
        'orden_numero' => $orderNumber,
        'archivo' => $uploadResult['path'],
        'telegram_user' => $username,
        'source' => 'telegram_webhook'
    ]);

    // Queue notification (if auto-send enabled)
    if ($appConfig['notifications']['auto_send']) {
        queueNotification($db, $orderNumber);
    }

    // Send confirmation to Telegram
    $fileTypeEmoji = $fileType === 'video' ? '🎥' : '📸';
    $confirmationMessage = "✅ {$fileTypeEmoji} Evidencia guardada\n\n" .
        "📋 Orden: #{$orderNumber}\n" .
        "📁 Archivo: {$downloadedFile['filename']}\n" .
        "💾 Tamaño: " . formatBytes($uploadResult['size']);

    sendTelegramMessage($chatId, $confirmationMessage, $botToken);

    // Return success
    echo json_encode([
        'ok' => true,
        'evidencia_id' => $evidenciaId,
        'orden_numero' => $orderNumber,
        'archivo_path' => $uploadResult['path']
    ]);

} catch (Exception $e) {
    error_log("[Telegram Webhook] Error: " . $e->getMessage());
    error_log("[Telegram Webhook] Stack trace: " . $e->getTraceAsString());

    // Try to notify user
    if (isset($chatId)) {
        sendTelegramMessage(
            $chatId,
            "❌ Error al procesar el archivo. Por favor contacta al administrador.",
            $botToken
        );
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Extract order number from text
 * Supports formats: "Orden: 12345", "#12345", "orden 12345"
 */
function extractOrderNumber($text)
{
    $patterns = [
        '/(?:orden|order)[\s:]*(\d+)/i',  // "Orden: 12345" or "orden 12345"
        '/#(\d+)/',                        // "#12345"
        '/\b(\d{4,})\b/'                   // Any 4+ digit number
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            return $matches[1];
        }
    }

    return null;
}

/**
 * Download file from Telegram
 */
function downloadTelegramFile($fileId, $botToken)
{
    try {
        // Get file path
        $fileInfoUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
        $fileInfo = file_get_contents($fileInfoUrl);
        $fileData = json_decode($fileInfo, true);

        if (!isset($fileData['result']['file_path'])) {
            error_log("[Telegram] Failed to get file path");
            return null;
        }

        $filePath = $fileData['result']['file_path'];
        $fileUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

        // Generate temp filename
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $tempFilename = uniqid('telegram_') . '.' . ($ext ?: 'bin');

        $appConfig = require __DIR__ . '/../api/config/app_config.php';
        $tempDir = $appConfig['files']['temp_dir'];

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir . '/' . $tempFilename;

        // Download file
        $fileContent = file_get_contents($fileUrl);
        if ($fileContent === false) {
            error_log("[Telegram] Failed to download file from: {$fileUrl}");
            return null;
        }

        file_put_contents($tempPath, $fileContent);

        return [
            'path' => $tempPath,
            'filename' => $tempFilename,
            'original_path' => $filePath
        ];

    } catch (Exception $e) {
        error_log("[Telegram] Download error: " . $e->getMessage());
        return null;
    }
}

/**
 * Send message to Telegram chat
 */
function sendTelegramMessage($chatId, $text, $botToken)
{
    try {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $response = json_decode($result, true);

        // Log the attempt
        error_log("[Telegram] Send message attempt - Chat ID: {$chatId}, HTTP: {$httpCode}");

        if ($curlError) {
            error_log("[Telegram] CURL Error: {$curlError}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("[Telegram] HTTP Error {$httpCode}: " . ($response['description'] ?? $result));
            return null;
        }

        if ($response && !$response['ok']) {
            error_log("[Telegram] API Error: " . ($response['description'] ?? 'Unknown error'));
            return null;
        }

        error_log("[Telegram] ✅ Message sent successfully to chat {$chatId}");
        return $response;

    } catch (Exception $e) {
        error_log("[Telegram] Send message exception: " . $e->getMessage());
        return null;
    }
}

/**
 * Queue notification for order
 */
function queueNotification($db, $orderNumber)
{
    try {
        // Get client data via bridge
        $bridgeService = new BridgeService($db);
        $clientData = $bridgeService->getClientByOrder($orderNumber);

        if (!$clientData) {
            error_log("[Telegram Webhook] No client data found for order: {$orderNumber}");
            return false;
        }

        // Generate gallery token (will implement later)
        // For now, just queue with placeholder URL

        $query = "
            INSERT INTO notificacion_queue (
                orden_numero, tipo, destinatario, mensaje, estado
            ) VALUES (
                :orden_numero, 'whatsapp', :telefono, 'Nueva evidencia disponible', 'pendiente'
            )
        ";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':orden_numero' => $orderNumber,
            ':telefono' => $clientData['cliente_telefono'] ?? ''
        ]);

        error_log("[Telegram Webhook] Notification queued for order: {$orderNumber}");
        return true;

    } catch (Exception $e) {
        error_log("[Telegram Webhook] Queue notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log audit trail
 */
function logAudit($db, $userId, $action, $entityType, $entityId, $details)
{
    try {
        $query = "
            INSERT INTO audit_logs (
                usuario_id, accion, entidad_tipo, entidad_id, detalles,
                ip_address, user_agent, timestamp
            ) VALUES (
                :usuario_id, :accion, :entidad_tipo, :entidad_id, :detalles,
                :ip_address, :user_agent, NOW()
            )
        ";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':usuario_id' => $userId,
            ':accion' => $action,
            ':entidad_tipo' => $entityType,
            ':entidad_id' => $entityId,
            ':detalles' => json_encode($details),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        return true;

    } catch (Exception $e) {
        error_log("[Audit] Log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Format bytes to human-readable format
 */
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