<?php
/**
 * JWT Helper Functions
 * For token generation and validation
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTHelper
{
    private static $config;

    private static function getConfig()
    {
        if (!self::$config) {
            $appConfig = require __DIR__ . '/../config/app_config.php';
            self::$config = $appConfig['jwt'];
        }
        return self::$config;
    }

    /**
     * Generate JWT token for user
     * 
     * @param array $userData User data (id, email, rol)
     * @return string JWT token
     */
    public static function generateToken($userData)
    {
        $config = self::getConfig();

        $issuedAt = time();
        $expirationTime = $issuedAt + $config['expiration'];

        $payload = [
            'iss' => $config['issuer'],
            'aud' => $config['audience'],
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'data' => [
                'user_id' => $userData['id'],
                'email' => $userData['email'],
                'rol' => $userData['rol']
            ]
        ];

        return JWT::encode($payload, $config['secret'], $config['algorithm']);
    }

    /**
     * Validate and decode JWT token
     * 
     * @param string $token JWT token
     * @return object|null Decoded token payload or null if invalid
     */
    public static function validateToken($token)
    {
        $config = self::getConfig();

        try {
            $decoded = JWT::decode($token, new Key($config['secret'], $config['algorithm']));
            return $decoded;
        } catch (Exception $e) {
            error_log("[JWT] Validation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract token from Authorization header
     * 
     * @return string|null Token or null if not found
     */
    public static function getTokenFromHeader()
    {
        $headers = getallheaders();

        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];

            // Format: "Bearer <token>"
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }

        // Fallback: check query parameter (for gallery access)
        if (isset($_GET['token'])) {
            return $_GET['token'];
        }

        return null;
    }

    /**
     * Get current user from token
     * 
     * @return array|null User data or null if not authenticated
     */
    public static function getCurrentUser()
    {
        $token = self::getTokenFromHeader();

        if (!$token) {
            return null;
        }

        $decoded = self::validateToken($token);

        if (!$decoded || !isset($decoded->data)) {
            return null;
        }

        return (array) $decoded->data;
    }

    /**
     * Check if user has required role
     * 
     * @param array $allowedRoles Array of allowed roles
     * @return bool
     */
    public static function hasRole($allowedRoles)
    {
        $user = self::getCurrentUser();

        if (!$user) {
            return false;
        }

        return in_array($user['rol'], $allowedRoles);
    }

    /**
     * Require authentication (or return 401)
     * 
     * @param array $allowedRoles Optional array of allowed roles
     * @return array User data
     */
    public static function requireAuth($allowedRoles = [])
    {
        $user = self::getCurrentUser();

        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'No autorizado. Token inválido o expirado.',
                'code' => 'UNAUTHORIZED'
            ]);
            exit;
        }

        if (!empty($allowedRoles) && !in_array($user['rol'], $allowedRoles)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'No tienes permisos para realizar esta acción.',
                'code' => 'FORBIDDEN'
            ]);
            exit;
        }

        return $user;
    }
}
?>