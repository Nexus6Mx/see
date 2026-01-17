<?php
/**
 * Simulate complete notification flow
 * Directly insert test notification into queue and process it
 */

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../services/BridgeService.php';

echo "🧪 Testing Complete Notification Flow\n";
echo "=====================================\n\n";

// Connect to database
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("❌ Database connection failed\n");
}

// Step 1: Test BridgeService
echo "Step 1: Testing BridgeService...\n";
$bridgeService = new BridgeService($db);
$clientData = $bridgeService->getClientByOrder('12345');

if ($clientData) {
    echo "✅ Client data found:\n";
    echo "   Name: {$clientData['cliente_nombre']}\n";
    echo "   Email: {$clientData['cliente_email']}\n";
    echo "   Phone: {$clientData['cliente_telefono']}\n";
    echo "   Vehicle: {$clientData['vehiculo_modelo']}\n\n";
} else {
    die("❌ No client data found\n");
}

// Step 2: Generate gallery link (simulate)
echo "Step 2: Generating gallery link...\n";
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

try {
    $query = "
        INSERT INTO galeria_tokens (token_hash, orden_numero, expira_en, creado_por_usuario_id)
        VALUES (:token_hash, :orden_numero, :expira_en, 1)
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':token_hash' => $tokenHash,
        ':orden_numero' => '12345',
        ':expira_en' => $expiresAt
    ]);

    $galleryUrl = "https://see.errautomotriz.online/galeria.php?t={$token}";
    echo "✅ Gallery link: {$galleryUrl}\n\n";
} catch (PDOException $e) {
    echo "⚠️  Gallery token: " . $e->getMessage() . "\n\n";
    $galleryUrl = "https://see.errautomotriz.online/galeria.php?t=test";
}

// Step 3: Queue notification
echo "Step 3: Queuing email notification...\n";
$notificationService = new NotificationService($db);

$emailData = $notificationService->generateMessage('email', [
    'cliente_nombre' => $clientData['cliente_nombre'],
    'vehiculo_modelo' => $clientData['vehiculo_modelo'],
    'orden_numero' => '12345',
    'galeria_url' => $galleryUrl
]);

$queued = $notificationService->queue(
    '12345',
    'email',
    $clientData['cliente_email'],
    $emailData['body'],
    $galleryUrl,
    1 // High priority for test
);

if ($queued) {
    echo "✅ Notification queued successfully\n\n";
} else {
    die("❌ Failed to queue notification\n");
}

// Step 4: Check queue
echo "Step 4: Checking notification queue...\n";
$checkQuery = "SELECT * FROM notificacion_queue WHERE orden_numero = '12345' ORDER BY created_at DESC LIMIT 1";
$stmt = $db->query($checkQuery);
$queuedNotif = $stmt->fetch(PDO::FETCH_ASSOC);

if ($queuedNotif) {
    echo "✅ Found in queue:\n";
    echo "   ID: {$queuedNotif['id']}\n";
    echo "   Type: {$queuedNotif['tipo']}\n";
    echo "   To: {$queuedNotif['destinatario']}\n";
    echo "   Status: {$queuedNotif['estado']}\n";
    echo "   Priority: {$queuedNotif['prioridad']}\n\n";
} else {
    die("❌ Not found in queue\n");
}

// Step 5: Process queue
echo "Step 5: Processing notification queue...\n";
$stats = $notificationService->processQueue(10);
echo "   Processed: {$stats['processed']}\n";
echo "   Success: {$stats['success']}\n  ";
echo "   Failed: {$stats['failed']}\n\n";

// Step 6: Verify sent
echo "Step 6: Verifying sent status...\n";
$verifyQuery = "SELECT * FROM notificacion_queue WHERE id = :id";
$stmt = $db->prepare($verifyQuery);
$stmt->execute([':id' => $queuedNotif['id']]);
$finalStatus = $stmt->fetch(PDO::FETCH_ASSOC);

if ($finalStatus['estado'] === 'enviado') {
    echo "✅ Email sent successfully!\n";
    echo "   Sent at: {$finalStatus['enviado_fecha']}\n";
    echo "   Check inbox: {$clientData['cliente_email']}\n\n";
    echo "🎉 TEST COMPLETE - All systems working!\n";
} else {
    echo "⚠️  Status: {$finalStatus['estado']}\n";
    echo "   Attempts: {$finalStatus['intentos']}\n";
    echo "   Last error: {$finalStatus['ultimo_error']}\n";
}
?>