<?php
/**
 * Database Configuration for SEE System
 * Independent connection to db_evidencias
 */

class Database {
    // Database credentials - UPDATE THESE FOR PRODUCTION
    private $host = "localhost";
    private $db_name = "u185421649_see_db";
    private $username = "u185421649_see_user";
    private $password = "3Errauto!";
    private $charset = "utf8mb4";

    private $conn;

    /**
     * Get PDO database connection
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            // Don't expose details in production
            error_log("[SEE Database] Connection error: " . $exception->getMessage());
            
            // In development, you might want to see the error
            if ($_ENV['APP_ENV'] === 'development' || (defined('APP_ENV') && APP_ENV === 'development')) {
                throw new Exception("Database connection failed: " . $exception->getMessage());
            }
        }

        return $this->conn;
    }

    /**
     * Test database connection
     * @return bool
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                $stmt = $conn->query("SELECT 1");
                return $stmt !== false;
            }
            return false;
        } catch (Exception $e) {
            error_log("[SEE Database] Test connection failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get database info
     * @return array
     */
    public function getInfo() {
        return [
            'host' => $this->host,
            'database' => $this->db_name,
            'charset' => $this->charset
        ];
    }
}
?>
