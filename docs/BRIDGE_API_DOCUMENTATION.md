# Bridge API - Documentación para taller-automotriz-app

## Propósito

Esta API proporciona acceso **read-only** a datos de órdenes y clientes desde el sistema principal (`taller-automotriz-app`) hacia el Sistema de Evidencia ERR (SEE).

**Principio fundamental**: Mínima intrusión en el sistema productivo.

---

## Especificación del Endpoint

### `GET /api/bridge/get_client_by_order.php`

**Descripción**: Retorna información de cliente y vehículo asociado a un número de orden.

**Autenticación**: API Key en header `X-API-Key`

**Rate Limiting**: 100 requests/minuto por IP (configurar en .htaccess - opcional)

---

## Request

### Headers

```http
GET /api/bridge/get_client_by_order.php?orden=12345 HTTP/1.1
Host: errautomotriz.online
X-API-Key: SEE_BRIDGE_API_KEY_2026 Connection: keep-alive
```

### Query Parameters

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `orden` | string | ✅ Sí | Número de orden de servicio |

---

## Response

### Success (200 OK)

```json
{
  "success": true,
  "data": {
    "orden_numero": "12345",
    "cliente_id": 42,
    "cliente_nombre": "Juan Pérez García",
    "cliente_telefono": "+525512345678",
    "cliente_email": "juan.perez@example.com",
    "vehiculo_modelo": "Toyota Corolla 2020",
    "vehiculo_placas": "ABC1234",
    "fecha_orden": "2026-01-09 10:30:00"
  }
}
```

### Error: Orden No Encontrada (404 Not Found)

```json
{
  "success": false,
  "error": "Orden no encontrada",
  "code": "ORDER_NOT_FOUND"
}
```

### Error: API Key Inválida (401 Unauthorized)

```json
{
  "success": false,
  "error": "API Key inválida o faltante",
  "code": "INVALID_API_KEY"
}
```

### Error: Parámetro Faltante (400 Bad Request)

```json
{
  "success": false,
  "error": "Parámetro 'orden' requerido",
  "code": "MISSING_PARAMETER"
}
```

---

## Implementación

### Archivo: `/api/bridge/get_client_by_order.php`

```php
<?php
/**
 * Bridge API - Read-only endpoint for SEE system
 * Returns client and vehicle information by order number
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://see.errautomotriz.online');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: X-API-Key');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Validate API Key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$validApiKey = 'SEE_BRIDGE_API_KEY_2026';  // TODO: Move to env variable

if ($apiKey !== $validApiKey) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'API Key inválida o faltante',
        'code' => 'INVALID_API_KEY'
    ]);
    exit();
}

// Get and validate order number
$ordenNumero = $_GET['orden'] ?? '';

if (empty($ordenNumero)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Parámetro \'orden\' requerido',
        'code' => 'MISSING_PARAMETER'
    ]);
    exit();
}

// Database connection
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Query: Get order with client and vehicle info
    $query = "
        SELECT 
            o.numero_orden,
            o.fecha_creacion,
            c.id AS cliente_id,
            c.nombre AS cliente_nombre,
            c.telefono AS cliente_telefono,
            c.email AS cliente_email,
            v.marca_modelo AS vehiculo_modelo,
            v.placas AS vehiculo_placas
        FROM ordenes o
        LEFT JOIN clientes c ON o.cliente_id = c.id
        LEFT JOIN vehiculos v ON o.vehiculo_id = v.id
        WHERE o.numero_orden = :orden_numero
        LIMIT 1
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':orden_numero', $ordenNumero, PDO::PARAM_STR);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Orden no encontrada',
            'code' => 'ORDER_NOT_FOUND'
        ]);
        exit();
    }
    
    // Format response
    $response = [
        'success' => true,
        'data' => [
            'orden_numero' => $result['numero_orden'],
            'cliente_id' => (int)$result['cliente_id'],
            'cliente_nombre' => $result['cliente_nombre'] ?? 'N/A',
            'cliente_telefono' => $result['cliente_telefono'] ?? '',
            'cliente_email' => $result['cliente_email'] ?? '',
            'vehiculo_modelo' => $result['vehiculo_modelo'] ?? 'N/A',
            'vehiculo_placas' => $result['vehiculo_placas'] ?? '',
            'fecha_orden' => $result['fecha_creacion']
        ]
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('[Bridge API] Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'code' => 'INTERNAL_ERROR'
    ]);
}
?>
```

---

## Seguridad

### 1. API Key Rotation

**Recomendación**: Rotar la API key cada 6 meses.

**Proceso**:
1. Generar nueva key: `openssl rand -hex 32`
2. Actualizar en ambos sistemas (bridge API y SEE config)
3. Deploy coordinado
4. Verificar funcionamiento
5. Invalidar key antigua

### 2. IP Whitelisting (Opcional)

Si Hostinger permite configurar firewall, limitar acceso solo desde:
- IP del servidor donde está desplegado SEE
- IPs de administradores para testing

**Archivo**: `/api/bridge/.htaccess`

```apache
<Files "get_client_by_order.php">
    Order Deny,Allow
    Deny from all
    Allow from 192.168.1.100  # IP del servidor SEE (ejemplo)
    Allow from 203.0.113.50   # IP de admin (ejemplo)
</Files>
```

### 3. Rate Limiting (Opcional)

Prevenir abuso con mod_evasive o similar:

```apache
<IfModule mod_evasive20.c>
    DOSHashTableSize 3097
    DOSPageCount 10
    DOSSiteCount 100
    DOSPageInterval 1
    DOSSiteInterval 1
    DOSBlockingPeriod 10
</IfModule>
```

---

## Testing

### Manual Testing con cURL

```bash
# Test exitoso
curl -H "X-API-Key: SEE_BRIDGE_API_KEY_2026" \
     "https://errautomotriz.online/api/bridge/get_client_by_order.php?orden=12345"

# Expected: JSON con datos del cliente

# Test API key inválida
curl -H "X-API-Key: wrong_key" \
     "https://errautomotriz.online/api/bridge/get_client_by_order.php?orden=12345"

# Expected: 401 Unauthorized

# Test orden inexistente
curl -H "X-API-Key: SEE_BRIDGE_API_KEY_2026" \
     "https://errautomotriz.online/api/bridge/get_client_by_order.php?orden=99999"

# Expected: 404 Not Found
```

### Testing desde SEE System

```php
// services/BridgeService.php
$bridge = new BridgeService();
$clientData = $bridge->getClientByOrder('12345');

var_dump($clientData);
// Expected: array con datos del cliente o null si falla
```

---

## Monitoreo

### Logs Recomendados

Agregar logging de todas las requests al bridge:

```php
// Después de validar API key exitosamente
error_log(sprintf(
    '[Bridge API] Request from %s for order %s at %s',
    $_SERVER['REMOTE_ADDR'],
    $ordenNumero,
    date('Y-m-d H:i:s')
));
```

### Métricas a Rastrear

1. **Total de requests por día**
2. **Latencia promedio de respuesta**
3. **Tasa de errores 404** (órdenes no encontradas)
4. **Requests con API key inválida** (posibles ataques)

---

## Rollback Plan

Si el bridge API causa problemas:

1. **Desactivar endpoint** renombrando archivo:
   ```bash
   mv get_client_by_order.php get_client_by_order.php.disabled
   ```

2. **Verificar** que el sistema principal sigue funcionando normalmente

3. **SEE system** continuará recibiendo evidencias pero sin poder enviar notificaciones automáticas (degradación graceful)

---

## Preguntas Frecuentes

**Q: ¿Este endpoint puede modificar datos?**
A: No. Es 100% read-only. Solo hace SELECT queries.

**Q: ¿Qué pasa si el bridge API está lento?**
A: SEE tiene timeout de 5 segundos. Si el bridge no responde, continúa operando y encola la notificación para reintento posterior.

**Q: ¿Necesito modificar la base de datos principal?**
A: No. El bridge API usa las tablas existentes sin modificaciones.

**Q: ¿Cómo sé si alguien está abusando del endpoint?**
A: Revisa los logs de Apache/Nginx. Requests legítimos solo vendrán del sistema SEE (~50-100/día).

---

## Deployment Checklist

- [ ] Crear carpeta `/api/bridge/` en taller-automotriz-app
- [ ] Subir archivo `get_client_by_order.php`
- [ ] Verificar que `../config/database.php` sea accesible
- [ ] Generar API key segura y compartirla con equipo SEE
- [ ] Probar con cURL desde servidor SEE
- [ ] Configurar logging (opcional)
- [ ] Configurar .htaccess para IP whitelisting (opcional)
- [ ] Documentar en DOCUMENTACION.md del sistema principal

---

**Última actualización**: 2026-01-09
**Versión**: 1.0
**Mantenedor**: Nexus6 Consulting
