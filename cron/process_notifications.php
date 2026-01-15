<?php
/**
 * Notification Queue Processor - Cron Job
 * Processes pending notifications from the queue
 * 
 * Schedule: Run every 5 minutes
 * Crontab: (every 5 minutes) /usr/bin/php /path/to/see/cron/process_notifications.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line');
}

// Set working directory
chdir(__DIR__ . '/..');

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../services/NotificationService.php';

// Start processing
echo "[" . date('Y-m-d H:i:s') . "] Starting notification queue processor\n";

try {
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Initialize notification service
    $notificationService = new NotificationService($db);

    // Process queue (max 100 notifications per run)
    $limit = 100;
    $stats = $notificationService->processQueue($limit);

    // Log results
    if ($stats['processed'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Processed: {$stats['processed']}, Success: {$stats['success']}, Failed: {$stats['failed']}\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] No pending notifications\n";
    }

    // Get current queue stats
    $queueStats = $notificationService->getQueueStats();
    if ($queueStats) {
        echo "[" . date('Y-m-d H:i:s') . "] Queue stats (last 7 days): ";
        echo "Pending: {$queueStats['pendientes']}, ";
        echo "Sent: {$queueStats['enviados']}, ";
        echo "Failed: {$queueStats['fallidos']}\n";

        // Alert if too many failed
        if ($queueStats['fallidos'] > 20) {
            error_log("[ALERT] High number of failed notifications: {$queueStats['fallidos']}");
            echo "[WARNING] High number of failed notifications detected!\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Queue processor finished successfully\n";
    exit(0);

} catch (Exception $e) {
    error_log("[Notification Processor] Fatal error: " . $e->getMessage());
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
?>