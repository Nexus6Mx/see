<?php
/**
 * Send test evidence to Telegram bot
 * Simulates a user sending an image to the bot
 */

$botToken = '8183422633:AAGP2H90KsX05bEWNeYsMBzGpOEbEiWZsII';
$chatId = '7992639254'; // Your chat ID

// URL of a test image (using a placeholder)
$photoUrl = 'https://via.placeholder.com/800x600.png/0066cc/ffffff?text=Test+Evidence+Order+12345';

// Send photo with caption #12345
$url = "https://api.telegram.org/bot{$botToken}/sendPhoto";

$data = [
    'chat_id' => $chatId,
    'photo' => $photoUrl,
    'caption' => '#12345'
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n";

$result = json_decode($response, true);
if (isset($result['ok']) && $result['ok']) {
    echo "\n✅ Photo sent successfully to bot!\n";
    echo "Message ID: " . $result['result']['message_id'] . "\n";
    echo "\nNow the bot should process it and queue the notification.\n";
    echo "Wait a moment and check the notificacion_queue table.\n";
} else {
    echo "\n❌ Failed to send photo\n";
    echo "Error: " . ($result['description'] ?? 'Unknown error') . "\n";
}
?>