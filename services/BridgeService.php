<?php
/**
 * BridgeService - Communication with Main System
 * Provides read-only access to client data from taller-automotriz-app
 * Includes caching and resilience features
 */

class BridgeService
{
    private $config;
    private $db;
    private $cacheEnabled;

    public function __construct($dbConnection = null)
    {
        $this->config = require __DIR__ . '/../api/config/bridge_config.php';
        $this->db = $dbConnection;
        $this->cacheEnabled = $this->config['cache']['enabled'] && $this->db !== null;
    }

    /**
     * Get client data by order number
     * 
     * @param string $orderNumber Order number
     * @param bool $skipCache Force fresh API call
     * @return array|null Client data or null if not found/failed
     */
    public function getClientByOrder($orderNumber, $skipCache = false)
    {
        // TEMPORARY: Hardcoded test data while bridge API is being configured
        // TODO: Remove this once bridge API is ready
        $testData = $this->getTestClientData($orderNumber);
        if ($testData !== null) {
            error_log("[BridgeService] Using TEST data for order: {$orderNumber}");
            return $testData;
        }

        // Try cache first
        if ($this->cacheEnabled && !$skipCache) {
            $cached = $this->getFromCache($orderNumber);
            if ($cached !== null) {
                error_log("[BridgeService] Cache hit for order: {$orderNumber}");
                return $cached;
            }
        }

        // Call bridge API
        $data = $this->callBridgeApi($orderNumber);

        // Cache the result if successful
        if ($data !== null && $this->cacheEnabled) {
            $this->saveToCache($orderNumber, $data);
        }

        return $data;
    }

    /**
     * TEMPORARY: Get test client data - Remove in production
     * 
     * @param string $orderNumber Order number
     * @return array|null
     */
    private function getTestClientData($orderNumber)
    {
        // Only for order #12345 (test order)
        if ($orderNumber === '12345') {
            return [
                'orden_numero' => '12345',
                'cliente_nombre' => 'Carlos Barba',
                'cliente_email' => 'cbarbap@gmail.com',
                'cliente_telefono' => '5577190053',
                'vehiculo_modelo' => 'Honda Civic EX 2020',
                'vehiculo_placas' => 'ERR-TEST',
                'fecha_orden' => date('Y-m-d H:i:s')
            ];
        }

        return null;
    }

    /**
     * Call bridge API endpoint
     * 
     * @param string $orderNumber Order number
     * @return array|null
     */
    private function callBridgeApi($orderNumber)
    {
        $url = $this->config['api_url'] . '?orden=' . urlencode($orderNumber);
        $apiKey = $this->config['api_key'];

        $retries = $this->config['retry']['enabled'] ? $this->config['retry']['max_attempts'] : 1;
        $attempt = 0;

        while ($attempt < $retries) {
            $attempt++;

            try {
                $ch = curl_init($url);

                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'X-API-Key: ' . $apiKey,
                        'Accept: application/json'
                    ],
                    CURLOPT_TIMEOUT => $this->config['timeout'],
                    CURLOPT_CONNECTTIMEOUT => $this->config['connect_timeout'],
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_FOLLOWLOCATION => true
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($error) {
                    throw new Exception("cURL error: {$error}");
                }

                if ($httpCode === 200) {
                    $data = json_decode($response, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("Invalid JSON response");
                    }

                    if (isset($data['success']) && $data['success'] === true) {
                        error_log("[BridgeService] Successfully fetched client data for order: {$orderNumber}");
                        return $data['data'];
                    } else {
                        error_log("[BridgeService] API returned error: " . ($data['error'] ?? 'Unknown error'));
                        return null;
                    }
                } elseif ($httpCode === 404) {
                    error_log("[BridgeService] Order not found: {$orderNumber}");
                    return null;
                } else {
                    throw new Exception("HTTP {$httpCode}: {$response}");
                }

            } catch (Exception $e) {
                error_log("[BridgeService] Attempt {$attempt}/{$retries} failed: " . $e->getMessage());

                if ($attempt < $retries) {
                    // Wait before retry
                    sleep($this->config['retry']['delay_seconds']);
                    continue;
                }

                // All retries exhausted
                if ($this->config['fallback']['log_failures']) {
                    $this->logFailure($orderNumber, $e->getMessage());
                }

                return null;
            }
        }

        return null;
    }

    /**
     * Get client data from cache
     * 
     * @param string $orderNumber Order number
     * @return array|null
     */
    private function getFromCache($orderNumber)
    {
        if (!$this->db)
            return null;

        try {
            $query = "
                SELECT * FROM cache_client_data 
                WHERE orden_numero = :orden_numero 
                AND expires_at > NOW()
                LIMIT 1
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':orden_numero', $orderNumber);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'orden_numero' => $row['orden_numero'],
                    'cliente_id' => $row['cliente_id'],
                    'cliente_nombre' => $row['cliente_nombre'],
                    'cliente_telefono' => $row['cliente_telefono'],
                    'cliente_email' => $row['cliente_email'],
                    'vehiculo_modelo' => $row['vehiculo_modelo'],
                    'vehiculo_placas' => $row['vehiculo_placas'],
                    'fecha_orden' => $row['fecha_orden']
                ];
            }

            return null;

        } catch (PDOException $e) {
            error_log("[BridgeService] Cache read error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Save client data to cache
     * 
     * @param string $orderNumber Order number
     * @param array $data Client data
     */
    private function saveToCache($orderNumber, $data)
    {
        if (!$this->db)
            return;

        try {
            $ttlHours = $this->config['cache']['ttl_hours'];
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$ttlHours} hours"));

            $query = "
                INSERT INTO cache_client_data (
                    orden_numero, cliente_id, cliente_nombre, cliente_telefono,
                    cliente_email, vehiculo_modelo, vehiculo_placas, fecha_orden,
                    cached_at, expires_at
                ) VALUES (
                    :orden_numero, :cliente_id, :cliente_nombre, :cliente_telefono,
                    :cliente_email, :vehiculo_modelo, :vehiculo_placas, :fecha_orden,
                    NOW(), :expires_at
                )
                ON DUPLICATE KEY UPDATE
                    cliente_id = VALUES(cliente_id),
                    cliente_nombre = VALUES(cliente_nombre),
                    cliente_telefono = VALUES(cliente_telefono),
                    cliente_email = VALUES(cliente_email),
                    vehiculo_modelo = VALUES(vehiculo_modelo),
                    vehiculo_placas = VALUES(vehiculo_placas),
                    fecha_orden = VALUES(fecha_orden),
                    cached_at = NOW(),
                    expires_at = VALUES(expires_at)
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':orden_numero' => $orderNumber,
                ':cliente_id' => $data['cliente_id'] ?? null,
                ':cliente_nombre' => $data['cliente_nombre'] ?? null,
                ':cliente_telefono' => $data['cliente_telefono'] ?? null,
                ':cliente_email' => $data['cliente_email'] ?? null,
                ':vehiculo_modelo' => $data['vehiculo_modelo'] ?? null,
                ':vehiculo_placas' => $data['vehiculo_placas'] ?? null,
                ':fecha_orden' => $data['fecha_orden'] ?? null,
                ':expires_at' => $expiresAt
            ]);

            error_log("[BridgeService] Cached client data for order: {$orderNumber}");

        } catch (PDOException $e) {
            error_log("[BridgeService] Cache write error: " . $e->getMessage());
            // Non-critical error, don't throw
        }
    }

    /**
     * Clear cache for specific order
     * 
     * @param string $orderNumber Order number
     */
    public function clearCache($orderNumber)
    {
        if (!$this->db)
            return;

        try {
            $query = "DELETE FROM cache_client_data WHERE orden_numero = :orden_numero";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':orden_numero', $orderNumber);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("[BridgeService] Cache clear error: " . $e->getMessage());
        }
    }

    /**
     * Clear all expired cache entries
     */
    public function clearExpiredCache()
    {
        if (!$this->db)
            return;

        try {
            $query = "DELETE FROM cache_client_data WHERE expires_at < NOW()";
            $stmt = $this->db->prepare($query);
            $affected = $stmt->execute();

            error_log("[BridgeService] Cleared {$affected} expired cache entries");
            return $affected;

        } catch (PDOException $e) {
            error_log("[BridgeService] Expired cache clear error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Log bridge API failure
     * 
     * @param string $orderNumber Order number
     * @param string $error Error message
     */
    private function logFailure($orderNumber, $error)
    {
        error_log("[BridgeService] FAILURE for order {$orderNumber}: {$error}");

        // TODO: Implement alert mechanism after N consecutive failures
        // Could use a failures table or file-based counter
    }

    /**
     * Test bridge API connection
     * 
     * @return bool
     */
    public function testConnection()
    {
        try {
            // Try to fetch a dummy order (will return 404 but proves API is reachable)
            $url = $this->config['api_url'] . '?orden=TEST_CONNECTION';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $this->config['api_key']
                ],
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 200, 404, or 401 all mean the API is reachable
            return in_array($httpCode, [200, 404, 401, 400]);

        } catch (Exception $e) {
            error_log("[BridgeService] Connection test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function getCacheStats()
    {
        if (!$this->db)
            return ['enabled' => false];

        try {
            $query = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN expires_at <= NOW() THEN 1 ELSE 0 END) as expired
                FROM cache_client_data
            ";

            $stmt = $this->db->query($query);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'enabled' => true,
                'total' => (int) $stats['total'],
                'active' => (int) $stats['active'],
                'expired' => (int) $stats['expired'],
                'ttl_hours' => $this->config['cache']['ttl_hours']
            ];

        } catch (PDOException $e) {
            error_log("[BridgeService] Cache stats error: " . $e->getMessage());
            return ['enabled' => true, 'error' => $e->getMessage()];
        }
    }
}
?>