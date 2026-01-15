<?php
/**
 * NotificationService - Multi-channel notification dispatcher
 * Supports WhatsApp (Evolution API), Telegram, and Email
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class NotificationService
{
    private $config;
    private $db;

    public function __construct($dbConnection = null)
    {
        $appConfig = require __DIR__ . '/../api/config/app_config.php';
        $this->config = $appConfig['notifications'];
        $this->db = $dbConnection;
    }

    /**
     * Send notification via specified channel
     * 
     * @param string $tipo Channel: whatsapp, telegram, email
     * @param string $destinatario Recipient (phone, telegram_id, email)
     * @param string $mensaje Message text
     * @param array $extra Extra data (gallery_url, etc.)
     * @return array ['success' => bool, 'response' => mixed, 'error' => string|null]
     */
    public function send($tipo, $destinatario, $mensaje, $extra = [])
    {
        try {
            switch ($tipo) {
                case 'whatsapp':
                    return $this->sendWhatsApp($destinatario, $mensaje, $extra);

                case 'telegram':
                    return $this->sendTelegram($destinatario, $mensaje);

                case 'email':
                    return $this->sendEmail($destinatario, $mensaje, $extra);

                default:
                    return [
                        'success' => false,
                        'error' => "Tipo de notificación no soportado: {$tipo}"
                    ];
            }
        } catch (Exception $e) {
            error_log("[NotificationService] Error sending {$tipo}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send WhatsApp message via Evolution API
     * 
     * @param string $phoneNumber Phone number with country code
     * @param string $message Message text
     * @param array $extra Extra options
     * @return array
     */
    private function sendWhatsApp($phoneNumber, $message, $extra = [])
    {
        if (!$this->config['whatsapp']['enabled']) {
            return ['success' => false, 'error' => 'WhatsApp notifications disabled'];
        }

        // Format phone number (remove + and spaces)
        $phone = preg_replace('/[^0-9]/', '', $phoneNumber);

        if (empty($phone)) {
            return ['success' => false, 'error' => 'Invalid phone number'];
        }

        $apiUrl = rtrim($this->config['whatsapp']['api_url'], '/');
        $apiKey = $this->config['whatsapp']['api_key'];
        $instance = $this->config['whatsapp']['instance_name'];

        // Evolution API endpoint
        $endpoint = "{$apiUrl}/message/sendText/{$instance}";

        $payload = [
            'number' => $phone,
            'text' => $message
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'apikey: ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => "cURL error: {$error}"];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("[NotificationService] WhatsApp sent to {$phone}");
            return [
                'success' => true,
                'response' => $responseData
            ];
        } else {
            $errorMsg = $responseData['message'] ?? $response;
            return ['success' => false, 'error' => "HTTP {$httpCode}: {$errorMsg}"];
        }
    }

    /**
     * Send Telegram message
     * 
     * @param string $chatId Telegram chat ID
     * @param string $message Message text
     * @return array
     */
    private function sendTelegram($chatId, $message)
    {
        $appConfig = require __DIR__ . '/../api/config/app_config.php';
        $botToken = $appConfig['telegram']['bot_token'];

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode($response, true);

        if ($httpCode === 200 && isset($responseData['ok']) && $responseData['ok']) {
            error_log("[NotificationService] Telegram sent to {$chatId}");
            return [
                'success' => true,
                'response' => $responseData
            ];
        } else {
            $errorMsg = $responseData['description'] ?? 'Unknown error';
            return ['success' => false, 'error' => $errorMsg];
        }
    }

    /**
     * Send email via SMTP
     * 
     * @param string $emailAddress Email address
     * @param string $message Plain text message
     * @param array $extra Extra data (subject, html_body, etc.)
     * @return array
     */
    private function sendEmail($emailAddress, $message, $extra = [])
    {
        if (!$this->config['email']['enabled']) {
            return ['success' => false, 'error' => 'Email notifications disabled'];
        }

        try {
            $mail = new PHPMailer(true);

            // SMTP configuration
            $mail->isSMTP();
            $mail->Host = $this->config['email']['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['email']['smtp_user'];
            $mail->Password = $this->config['email']['smtp_password'];
            $mail->SMTPSecure = $this->config['email']['smtp_secure'];
            $mail->Port = $this->config['email']['smtp_port'];
            $mail->CharSet = 'UTF-8';

            // Sender
            $mail->setFrom(
                $this->config['email']['from_address'],
                $this->config['email']['from_name']
            );

            // Recipient
            $mail->addAddress($emailAddress);

            // Subject and body
            $subject = $extra['subject'] ?? 'Notificación de ERR Automotriz';
            $htmlBody = $extra['html_body'] ?? nl2br(htmlspecialchars($message));

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags($message);

            $mail->send();

            error_log("[NotificationService] Email sent to {$emailAddress}");
            return ['success' => true];

        } catch (PHPMailerException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Queue notification for later sending
     * 
     * @param string $ordenNumero Order number
     * @param string $tipo Channel type
     * @param string $destinatario Recipient
     * @param string $mensaje Message
     * @param string $galeriaUrl Gallery URL
     * @param int $prioridad Priority (1=high, 5=normal, 10=low)
     * @return bool Success
     */
    public function queue($ordenNumero, $tipo, $destinatario, $mensaje, $galeriaUrl = null, $prioridad = 5)
    {
        if (!$this->db) {
            error_log("[NotificationService] Cannot queue without database connection");
            return false;
        }

        try {
            $query = "
                INSERT INTO notificacion_queue (
                    orden_numero, tipo, destinatario, mensaje, galeria_url,
                    estado, prioridad, created_at
                ) VALUES (
                    :orden_numero, :tipo, :destinatario, :mensaje, :galeria_url,
                    'pendiente', :prioridad, NOW()
                )
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':orden_numero' => $ordenNumero,
                ':tipo' => $tipo,
                ':destinatario' => $destinatario,
                ':mensaje' => $mensaje,
                ':galeria_url' => $galeriaUrl,
                ':prioridad' => $prioridad
            ]);

            error_log("[NotificationService] Queued {$tipo} notification for order {$ordenNumero}");
            return true;

        } catch (PDOException $e) {
            error_log("[NotificationService] Queue error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process notification queue
     * Called by cron job
     * 
     * @param int $limit Max notifications to process
     * @return array ['processed' => int, 'success' => int, 'failed' => int]
     */
    public function processQueue($limit = 50)
    {
        if (!$this->db) {
            error_log("[NotificationService] Cannot process queue without database connection");
            return ['processed' => 0, 'success' => 0, 'failed' => 0];
        }

        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];

        try {
            // Get pending notifications
            $query = "
                SELECT * FROM notificacion_queue
                WHERE estado = 'pendiente'
                AND intentos < max_intentos
                ORDER BY prioridad ASC, created_at ASC
                LIMIT :limit
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($notifications as $notification) {
                $stats['processed']++;

                // Send notification
                $result = $this->send(
                    $notification['tipo'],
                    $notification['destinatario'],
                    $notification['mensaje'],
                    ['gallery_url' => $notification['galeria_url']]
                );

                // Update queue record
                if ($result['success']) {
                    $this->markAsSent($notification['id']);
                    $stats['success']++;
                } else {
                    $this->markAsFailed($notification['id'], $result['error']);
                    $stats['failed']++;
                }
            }

            if ($stats['processed'] > 0) {
                error_log("[NotificationService] Processed {$stats['processed']} notifications: {$stats['success']} success, {$stats['failed']} failed");
            }

            return $stats;

        } catch (Exception $e) {
            error_log("[NotificationService] Process queue error: " . $e->getMessage());
            return $stats;
        }
    }

    /**
     * Mark notification as sent
     * 
     * @param int $notificationId Notification queue ID
     */
    private function markAsSent($notificationId)
    {
        try {
            $query = "
                UPDATE notificacion_queue
                SET estado = 'enviado',
                    enviado_fecha = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $notificationId]);

        } catch (PDOException $e) {
            error_log("[NotificationService] Mark sent error: " . $e->getMessage());
        }
    }

    /**
     * Mark notification as failed and increment attempts
     * 
     * @param int $notificationId Notification queue ID
     * @param string $error Error message
     */
    private function markAsFailed($notificationId, $error)
    {
        try {
            $query = "
                UPDATE notificacion_queue
                SET intentos = intentos + 1,
                    ultimo_error = :error,
                    ultimo_intento_fecha = NOW(),
                    estado = CASE 
                        WHEN intentos + 1 >= max_intentos THEN 'fallido'
                        ELSE 'pendiente'
                    END,
                    updated_at = NOW()
                WHERE id = :id
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':id' => $notificationId,
                ':error' => $error
            ]);

        } catch (PDOException $e) {
            error_log("[NotificationService] Mark failed error: " . $e->getMessage());
        }
    }

    /**
     * Generate notification message from template
     * 
     * @param string $tipo Channel type
     * @param array $data Template variables
     * @return string Formatted message
     */
    public function generateMessage($tipo, $data)
    {
        $template = '';

        switch ($tipo) {
            case 'whatsapp':
            case 'telegram':
                $template = $this->config['templates']['whatsapp'];
                break;

            case 'email':
                return [
                    'subject' => str_replace(
                        ['{orden_numero}'],
                        [$data['orden_numero'] ?? ''],
                        $this->config['templates']['email_subject']
                    ),
                    'body' => str_replace(
                        ['{cliente_nombre}', '{vehiculo_modelo}', '{orden_numero}', '{galeria_url}'],
                        [
                            $data['cliente_nombre'] ?? 'Cliente',
                            $data['vehiculo_modelo'] ?? 'su vehículo',
                            $data['orden_numero'] ?? '',
                            $data['galeria_url'] ?? ''
                        ],
                        $this->config['templates']['email_body']
                    )
                ];
        }

        // Replace placeholders
        $message = str_replace(
            ['{cliente_nombre}', '{vehiculo_modelo}', '{orden_numero}', '{galeria_url}'],
            [
                $data['cliente_nombre'] ?? 'Cliente',
                $data['vehiculo_modelo'] ?? 'su vehículo',
                $data['orden_numero'] ?? '',
                $data['galeria_url'] ?? ''
            ],
            $template
        );

        return $message;
    }

    /**
     * Get queue statistics
     * 
     * @return array
     */
    public function getQueueStats()
    {
        if (!$this->db) {
            return null;
        }

        try {
            $query = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviados,
                    SUM(CASE WHEN estado = 'fallido' THEN 1 ELSE 0 END) as fallidos
                FROM notificacion_queue
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ";

            $stmt = $this->db->query($query);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("[NotificationService] Stats error: " . $e->getMessage());
            return null;
        }
    }
}
?>