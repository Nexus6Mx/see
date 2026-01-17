<?php
/**
 * Test script for Telegram bot responses
 * This script tests if sendTelegramMessage function works correctly
 */

require_once __DIR__ . '/../api/config/app_config.php';

// Load config
$appConfig = require __DIR__ . '/../api/config/app_config.php';
$botToken = $appConfig['telegram']['bot_token'];

// Get the last chat ID from recent updates
$updatesUrl = "https://api.telegram.org/bot{$botToken}/getUpdates";
$updatesResponse = file_get_contents($updatesUrl);
$updates = json_decode($updatesResponse, true);

if (!$updates['ok'] || empty($updates['result'])) {
    echo "❌ No se pudieron obtener actualizaciones del bot\n";
    echo "Response: " . $updatesResponse . "\n";
    exit(1);
}

// Get last chat ID
$lastUpdate = end($updates['result']);
$chatId = $lastUpdate['message']['chat']['id'] ?? null;

if (!$chatId) {
    echo "❌ No se encontró chat_id en las actualizaciones\n";
    echo "Por favor envía un mensaje al bot @usuariosee_bot primero\n";
    exit(1);
}

echo "✅ Chat ID encontrado: {$chatId}\n\n";

// Test sendTelegramMessage function
function sendTelegramMessage($chatId, $text, $botToken)
{
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

    echo "HTTP Code: {$httpCode}\n";
    if ($curlError) {
        echo "CURL Error: {$curlError}\n";
    }
    echo "Response: {$result}\n\n";

    return json_decode($result, true);
}

// Send test message
echo "📤 Enviando mensaje de prueba...\n";
$testMessage = "🧪 **Prueba de Bot SEE**\n\nSi recibes este mensaje, el bot está funcionando correctamente.\n\n✅ Respuestas automáticas: ACTIVAS";

$response = sendTelegramMessage($chatId, $testMessage, $botToken);

if ($response && $response['ok']) {
    echo "✅ Mensaje enviado exitosamente!\n";
    echo "Message ID: " . $response['result']['message_id'] . "\n";
} else {
    echo "❌ Error al enviar mensaje\n";
    if ($response) {
        echo "Error: " . ($response['description'] ?? 'Unknown error') . "\n";
    }
}
?>