<?php
/**
 * Test direct email send via PHPMailer
 */

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "🧪 Testing Direct Email Send\n";
echo "============================\n\n";

// Load config
$appConfig = require __DIR__ . '/api/config/app_config.php';
$emailConfig = $appConfig['notifications']['email'];

echo "Step 1: Creating PHPMailer instance...\n";
$mail = new PHPMailer(true);

try {
    // SMTP Configuration
    echo "Step 2: Configuring SMTP...\n";
    $mail->isSMTP();
    $mail->Host = $emailConfig['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $emailConfig['smtp_user'];
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = $emailConfig['smtp_secure'];
    $mail->Port = $emailConfig['smtp_port'];
    $mail->CharSet = 'UTF-8';

    // Enable debug output
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function ($str, $level) {
        echo "  [SMTP] $str\n";
    };

    echo "  Host: {$mail->Host}:{$mail->Port}\n";
    echo "  User: {$mail->Username}\n";
    echo "  Security: {$mail->SMTPSecure}\n\n";

    // Recipients
    echo "Step 3: Setting recipients...\n";
    $mail->setFrom($emailConfig['from_address'], $emailConfig['from_name']);
    $mail->addAddress('cbarbap@gmail.com', 'Carlos Barba');
    echo "  From: {$emailConfig['from_address']}\n";
    echo "  To: cbarbap@gmail.com\n\n";

    // Content
    echo "Step 4: Creating email content...\n";
    $mail->isHTML(true);
    $mail->Subject = '🧪 Test - Sistema de Evidencias ERR';
    $mail->Body = '
        <html>
        <body style="font-family: Arial, sans-serif;">
            <h2>Test de Notificaciones</h2>
            <p>Hola Carlos,</p>
            <p>Este es un email de prueba del sistema de notificaciones de ERR Automotriz.</p>
            <p>Si recibes este correo, significa que el SMTP está configurado correctamente.</p>
            <p style="margin-top: 30px;">
                <a href="https://see.errautomotriz.online" style="background-color:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;">
                    Ver Sistema
                </a>
            </p>
            <p style="margin-top: 30px; color: #666; font-size: 12px;">
                ERR Automotriz - Sistema de Evidencias<br>
                ' . date('Y-m-d H:i:s') . '
            </p>
        </body>
        </html>
    ';
    $mail->AltBody = 'Test de notificaciones del sistema SEE';

    // Send
    echo "Step 5: Sending email...\n\n";
    $mail->send();

    echo "\n✅ Email sent successfully!\n";
    echo "Check inbox: cbarbap@gmail.com\n";
    echo "(Also check spam/junk folder)\n";

} catch (Exception $e) {
    echo "\n❌ Error sending email:\n";
    echo "   " . $mail->ErrorInfo . "\n";
    echo "\nFull exception:\n";
    echo $e->getMessage() . "\n";
    echo "\nTrace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>