<?php
// Simple webhook test - no dependencies
header('Content-Type: application/json');

error_log("[Telegram Webhook Test] Ping received at " . date('Y-m-d H:i:s'));

echo json_encode([
    'ok' => true,
    'status' => 'webhook_alive',
    'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
    'time' => date('Y-m-d H:i:s')
]);
