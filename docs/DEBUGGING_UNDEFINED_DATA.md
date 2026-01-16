# 🐛 Debugging: Imágenes "undefined" en Dashboard

## Problema Reportado

Las evidencias muestran:
- Imagen rota (liga sin URL)  
- Texto "undefined" en nombre de archivo
- Fecha "Invalid Date"

## Causas Posibles

### 1. Los datos NO llegan de la API

**Verificar en consola del navegador:**

1. Abrir DevTools (F12)
2. Tab "Network"
3. Recargar página
4. Buscar llamada a: `read.php?group_by=orden`
5. Ver respuesta

**Verificar directamente la API:**

```bash
# Desde terminal local
curl -s "https://see.errautomotriz.online/api/evidencias/read.php?group_by=orden" \
  -H "Authorization: Bearer TU_TOKEN" | jq
```

**Respuesta esperada:**

```json
{
  "success": true,
  "grouped": true,
  "data": [
    {
      "orden_numero": "12345",
      "total_evidencias": 3,
      "evidencias": [
        {
          "id": 1,
          "archivo_url": "https://...",
          "thumbnail_url": "https://...",
          "archivo_nombre_original": "foto.jpg",
          "fecha_creacion": "2026-01-15 10:00:00"
        }
      ]
    }
  ]
}
```

### 2. La API NO tiene las evidencias

**Verificar en MySQL:**

```sql
SELECT COUNT(*) FROM evidencias WHERE estado = 'activo';
```

Si retorna 0 → No hay evidencias

**Solución:** Enviar evidencias al bot de Telegram primero

### 3. Error en el código

**Agregar console.log temporal en dashboard.js:**

Línea ~130, dentro de `loadEvidencias()`:

```javascript
const response = await api(`/api/evidencias/read.php?${params}`);

// AGREGAR ESTOS LOGS
console.log('API Response:', response);
console.log('Grouped Data:', response.data);
console.log('First Group:', response.data[0]);
console.log('First Evidence:', response.data[0]?.evidencias[0]);

if (response && response.success) {
    // ...
}
```

## Soluciones Paso a Paso

### Opción A: No hay evidencias en BD

**Acción:** Enviar foto al bot

1. Telegram → @usuariosee_bot
2. Mensaje:  `#12345`
3. Adjuntar una foto
4. Enviar
5. Recargar dashboard

### Opción B: API retorna datos vacíos

**Verificar configuración de `.env` en servidor:**

```bash
# SSH a Hostinger
cat /path/to/.env | grep DB_

# Debe mostrar:
# DB_NAME=u185421649_see_db
# DB_USER=u185421649_see_user  
# DB_PASS=3Errauto!
```

**Probar conexión a BD:**

Crear archivo temporal: `/public_html/test_db.php`

```php
<?php
require_once __DIR__ . '/api/config/database.php';

$db = new Database();
$conn = $db->getConnection();

if ($conn) {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM evidencias");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Evidencias en BD: " . $result['total'];
} else {
    echo "Error de conexión a BD";
}
?>
```

Acceder a: `https://see.errautomotriz.online/test_db.php`

**ELIMINAR archivo después de probar**

### Opción C: URLs de R2 incorrectas

**Verificar configuración R2:**

En `/api/config/r2_config.php` debe tener:

```php
'cdn_url' => 'https://pub-XXXXX.r2.dev'
```

**Probar URL directa:**

Tomar una URL de evidencia y abrirla en navegador:
- Si abre → R2 funciona
- Si 404 → Problema de configuración R2
- Si 403 → Problema de permisos en bucket

## Script de Debugging Automático

Crear: `/public_html/debug_evidencias.php`

```php
<?php
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/config/r2_config.php';

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

$debug = [];

// 1. Contar evidencias
$stmt = $conn->query("SELECT COUNT(*) as total FROM evidencias WHERE estado = 'activo'");
$debug['total_evidencias'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// 2. Muestra de evidencia
$stmt = $conn->query("SELECT * FROM evidencias WHERE estado = 'activo' LIMIT 1");
$evidencia = $stmt->fetch(PDO::FETCH_ASSOC);
$debug['sample_evidencia'] = $evidencia;

// 3. Config R2
$r2Config = require __DIR__ . '/api/config/r2_config.php';
$debug['cdn_url'] = $r2Config['cdn_url'];

// 4. URL completa generada
if ($evidencia) {
    $debug['generated_url'] = $r2Config['cdn_url'] . '/' . ltrim($evidencia['archivo_path'], '/');
}

echo json_encode($debug, JSON_PRETTY_PRINT);
?>
```

Acceder: `https://see.errautomotriz.online/debug_evidencias.php`

**ELIMINAR después de debuggear**

## Solución Rápida (Sin Debugging)

Si no quieres debuggear, simplemente:

1. **Envía nuevas evidencias vía Telegram**
   - Bot: @usuariosee_bot
   - Mensaje: `#99999`
   - Adjuntar foto nueva
   
2. **Refresca dashboard**
   - Ctrl + Shift + R (hard refresh)

3. **Verifica que aparezca la nueva evidencia**

Si la nueva aparece correctamente → Las anteriores tienen datos corruptos, ignóralas

## Checklist de Verificación

- [ ] Hay evidencias en la BD (`debug_evidencias.php`)
- [ ] API retorna datos correctos (Network tab)
- [ ] URLs de R2 son accesibles (abrir en navegador)
- [ ] `.env` tiene credenciales correctas
- [ ] Console.log muestra datos (F12 → Console)

---

**Siguiente paso recomendado:**

Abre el dashboard en producción con DevTools (F12) y revisa la pestaña **Console** y **Network** para ver qué datos llegan realmente.
