<?php
/**
 * Comprehensive Telegram Bot Diagnostic Script
 * Tests various methods to send Telegram messages
 */

header('Content-Type: text/plain; charset=utf-8');

$botToken = "8183422633:AAGP2H90KsX05bEWNeYsMBzGpOEbEiWZsII";
$testChatId = null;

echo "=== TELEGRAM BOT DIAGNOSTIC TOOL ===\n\n";

// Step 1: Get recent updates to find a chat_id
echo "[1] Getting recent updates from Telegram...\n";
$updatesUrl = "https://api.telegram.org/bot{$botToken}/getUpdates?limit=10";

try {
    $updatesJson = @file_get_contents($updatesUrl);
    if ($updatesJson === false) {
        echo "❌ file_get_contents failed for getUpdates\n";
        echo "Error: " . error_get_last()['message'] . "\n\n";
    } else {
        $updates = json_decode($updatesJson, true);
        if (!empty($updates['result'])) {
            // Find any message with a chat_id
            foreach ($updates['result'] as $update) {
                if (isset($update['message']['chat']['id'])) {
                    $testChatId = $update['message']['chat']['id'];
                    echo "✅ Found chat_id: {$testChatId}\n\n";
                    break;
                }
            }
        }
        if (!$testChatId) {
            echo "⚠️ No recent messages found. Please send /start to @usuariosee_bot\n\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n\n";
}

// Step 2: Check PHP configuration
echo "[2] Checking PHP configuration...\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'enabled' : 'disabled') . "\n";
echo "CURL extension: " . (extension_loaded('curl') ? 'installed' : 'NOT installed') . "\n";
echo "OpenSSL extension: " . (extension_loaded('openssl') ? 'installed' : 'NOT installed') . "\n\n";

if (!$testChatId) {
    echo "\n⚠️ Cannot continue without a chat_id. Please:\n";
    echo "1. Send /start to @usuariosee_bot\n";
    echo "2. Run this script again\n";
    exit;
}

// Step 3: Test CURL method
echo "[3] Testing CURL method...\n";
if (extension_loaded('curl')) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postData = [
        'chat_id' => $testChatId,
        'text' => '🧪 Test #1: CURL method - ' . date('H:i:s')
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_VERBOSE, false);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "HTTP Code: {$httpCode}\n";
    if ($curlError) {
        echo "CURL Error: {$curlError}\n";
    }

    $response = json_decode($result, true);
    if ($response && $response['ok']) {
        echo "✅ CURL method works!\n";
        echo "Message ID: " . $response['result']['message_id'] . "\n";
    } else {
        echo "❌ CURL method failed\n";
        echo "Response: " . substr($result, 0, 200) . "\n";
    }
} else {
    echo "❌ CURL not available\n";
}
echo "\n";

// Step 4: Test file_get_contents with stream context
echo "[4] Testing file_get_contents with POST...\n";
if (ini_get('allow_url_fopen')) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postData = http_build_query([
        'chat_id' => $testChatId,
        'text' => '🧪 Test #2: file_get_contents method - ' . date('H:i:s')
    ]);

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $postData,
            'timeout' => 10
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        echo "❌ file_get_contents failed\n";
        $error = error_get_last();
        echo "Error: " . ($error['message'] ?? 'Unknown') . "\n";
    } else {
        $response = json_decode($result, true);
        if ($response && $response['ok']) {
            echo "✅ file_get_contents method works!\n";
            echo "Message ID: " . $response['result']['message_id'] . "\n";
        } else {
            echo "❌ file_get_contents sent but API returned error\n";
            echo "Response: " . substr($result, 0, 200) . "\n";
        }
    }
} else {
    echo "❌ allow_url_fopen is disabled\n";
}
echo "\n";

// Step 5: Test CURL with JSON
echo "[5] Testing CURL with JSON payload...\n";
if (extension_loaded('curl')) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postData = json_encode([
        'chat_id' => $testChatId,
        'text' => '🧪 Test #3: CURL+JSON method - ' . date('H:i:s')
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
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

    $response = json_decode($result, true);
    if ($response && $response['ok']) {
        echo "✅ CURL+JSON method works!\n";
        echo "Message ID: " . $response['result']['message_id'] . "\n";
    } else {
        echo "❌ CURL+JSON method failed\n";
        echo "Response: " . substr($result, 0, 200) . "\n";
    }
} else {
    echo "❌ CURL not available\n";
}
echo "\n";

// Step 6: Summary
echo "=== SUMMARY ===\n";
echo "If you received 3 test messages in Telegram, all methods work!\n";
echo "If not, check which method succeeded and update the webhook accordingly.\n";
echo "\nCheck your Telegram app now: @usuariosee_bot\n";
?>