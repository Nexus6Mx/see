<?php
// Simple test to send a Telegram message from Hostinger
$botToken = "8183422633:AAGP2H90KsX05bEWNeYsMBzGpOEbEiWZsII";

// Get last chat ID from updates
$updatesUrl = "https://api.telegram.org/bot{$botToken}/getUpdates";
$updates = file_get_contents($updatesUrl);
$data = json_decode($updates, true);

if (empty($data['result'])) {
    die("No recent messages. Please send /start to the bot first.\n");
}

$lastUpdate = end($data['result']);
$chatId = $lastUpdate['message']['chat']['id'] ?? null;

if (!$chatId) {
    die("Could not extract chat_id\n");
}

echo "Chat ID: {$chatId}\n\n";

// Try to send message using CURL
$url = "https://api.telegram.org/bot{$botToken}/sendMessage";
$postData = [
    'chat_id' => $chatId,
    'text' => '🧪 Test from Hostinger server - ' . date('Y-m-d H:i:s')
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
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
echo "Response: {$result}\n";

$response = json_decode($result, true);
if ($response && $response['ok']) {
    echo "\n✅ SUCCESS! Message sent.\n";
} else {
    echo "\n❌ FAILED! Error: " . ($response['description'] ?? 'Unknown') . "\n";
}
?>