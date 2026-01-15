<?php
/**
 * Login API Endpoint
 * Authenticates users and returns JWT token
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/JWTHelper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Get request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

// Validate input
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Email y contraseña son requeridos',
        'code' => 'MISSING_FIELDS'
    ]);
    exit;
}

try {
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Find user by email
    $query = "SELECT * FROM users WHERE email = :email AND activo = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // User not found or inactive
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Credenciales inválidas',
            'code' => 'INVALID_CREDENTIALS'
        ]);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        // Invalid password
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Credenciales inválidas',
            'code' => 'INVALID_CREDENTIALS'
        ]);
        exit;
    }

    // Generate JWT token
    $token = JWTHelper::generateToken([
        'id' => $user['id'],
        'email' => $user['email'],
        'rol' => $user['rol']
    ]);

    // Log login
    $logQuery = "
        INSERT INTO audit_logs (
            usuario_id, accion, entidad_tipo, ip_address, user_agent, timestamp
        ) VALUES (
            :usuario_id, 'login', 'user', :ip_address, :user_agent, NOW()
        )
    ";
    $logStmt = $db->prepare($logQuery);
    $logStmt->execute([
        ':usuario_id' => $user['id'],
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login exitoso',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'rol' => $user['rol'],
            'nombre_completo' => $user['nombre_completo']
        ]
    ]);

} catch (Exception $e) {
    error_log("[Login API] Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'code' => 'INTERNAL_ERROR'
    ]);
}
?>