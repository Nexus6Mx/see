# Lista de Requerimientos para Cloudflare R2

Este documento detalla **exactamente** lo que necesitas configurar en tu cuenta de Cloudflare R2 antes de que el Sistema de Evidencia ERR (SEE) pueda ser desplegado.

---

## ✅ Checklist de Configuración

### 1. Crear Bucket de R2

**Acción**: Crear un nuevo bucket para almacenar las evidencias

**Pasos**:
1. Inicia sesión en Cloudflare Dashboard
2. Navega a: **R2 Object Storage** (menú lateral izquierdo)
3. Click en **"Create bucket"**
4. Configuración del bucket:
   - **Bucket name**: `err-evidencias` (exactamente este nombre)
   - **Location**: `Automatic` (deja que Cloudflare elija la mejor ubicación)
   - **Storage Class**: `Standard` (no usar Infrequent Access aún)

**Resultado esperado**: Bucket creado exitosamente

---

### 2. Configurar Acceso Público al Bucket

**Acción**: Permitir que los clientes puedan ver las evidencias desde sus galerías

**Opciones**:

#### Opción A: Dominio Personalizado (Recomendado)

**Ventajas**: URLs limpias, mejor branding, CDN gratuito de Cloudflare

**Pasos**:
1. En la página del bucket `err-evidencias`, ve a la pestaña **"Settings"**
2. Busca la sección **"Public access"** o **"Custom Domains"**
3. Click en **"Connect Domain"**
4. Ingresa el subdominio: `cdn.errautomotriz.online`
5. Cloudflare generará un registro DNS CNAME
6. **Importante**: Copia el valor del CNAME y agrégalo en tu zona DNS de `errautomotriz.online`
   - Tipo: `CNAME`
   - Nombre: `cdn`
   - Destino: `<bucket-id>.r2.cloudflarestorage.com` (Cloudflare te lo proporciona)
   - Proxy: ✅ Activado (naranja)
   - TTL: Auto
7. Espera la propagación DNS (~5-10 minutos)
8. Verifica: `https://cdn.errautomotriz.online/<año>/<mes>/<orden>/test.jpg` debe ser accesible

**Resultado esperado**: Archivos accesibles vía `https://cdn.errautomotriz.online/`

#### Opción B: Dev URL de Cloudflare (Más rápido, menos profesional)

Si no quieres configurar dominio personalizado todavía:

1. En Settings del bucket, busca **"Public R2.dev subdomain"**
2. Click en **"Allow Access"**
3. Cloudflare generará una URL como: `https://pub-<random>.r2.dev`
4. **Copia esta URL** y úsala como `R2_CDN_URL` en la configuración del sistema

**Resultado esperado**: Archivos accesibles vía `https://pub-<random>.r2.dev/`

---

### 3. Generar API Tokens (S3 Credentials)

**Acción**: Crear credenciales para que la aplicación PHP pueda subir archivos al bucket

**Pasos**:
1. En Cloudflare Dashboard, ve a **R2 Object Storage**
2. Click en **"Manage R2 API Tokens"** (botón en la parte superior derecha)
3. Click en **"Create API Token"**
4. Configuración del token:
   - **Token name**: `SEE System Upload Token`
   - **Permissions**:
     - ✅ **Object Read & Write** (necesario para subir y leer)
     - ❌ Admin Read & Write (NO seleccionar)
   - **Bucket scope**:
     - Seleccionar **"Apply to specific buckets only"**
     - Elegir: `err-evidencias`
   - **TTL**: Sin expiración (o 1 año si prefieres rotarlo)
5. Click en **"Create API Token"**

**Resultado**: Cloudflare mostrará las credenciales **una sola vez**:

```
Access Key ID: a1b2c3d4e5f6g7h8i9j0
Secret Access Key: K1L2M3N4O5P6Q7R8S9T0U1V2W3X4Y5Z6
Jurisdiction-specific endpoint for S3 clients: https://1234567890abcdef.r2.cloudflarestorage.com
```

**⚠️ IMPORTANTE**:
- **Guarda estas credenciales inmediatamente** en un lugar seguro (password manager)
- No podrás volver a ver el `Secret Access Key`
- Si lo pierdes, tendrás que generar un nuevo token

**Datos que necesitas copiar**:
1. ✅ **Access Key ID**
2. ✅ **Secret Access Key**
3. ✅ **Endpoint URL** (el que termina en `.r2.cloudflarestorage.com`)

---

### 4. Extraer Account ID

**Acción**: Obtener tu Cloudflare Account ID para configurar el SDK de AWS

**Pasos**:
1. En Cloudflare Dashboard, ve a cualquier página de R2
2. Busca en la URL del navegador. Verás algo como:
   ```
   https://dash.cloudflare.com/1234567890abcdef1234567890abcdef/r2/overview
                              ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
                                    Este es tu Account ID
   ```
3. **Copia el Account ID** (es el string hexadecimal largo)

**Resultado esperado**: Account ID de 32 caracteres alfanuméricos

---

### 5. Configurar CORS (Opcional pero Recomendado)

**Acción**: Permitir que la galería web cargue imágenes/videos desde el CDN sin errores de CORS

**Pasos**:
1. En la página del bucket `err-evidencias`, ve a **"Settings"**
2. Busca la sección **"CORS policy"**
3. Click en **"Edit CORS policy"** o **"Add CORS rule"**
4. Pega la siguiente configuración JSON:

```json
[
  {
    "AllowedOrigins": [
      "https://see.errautomotriz.online",
      "https://errautomotriz.online"
    ],
    "AllowedMethods": [
      "GET",
      "HEAD"
    ],
    "AllowedHeaders": [
      "*"
    ],
    "ExposeHeaders": [],
    "MaxAgeSeconds": 3600
  }
]
```

5. Guarda los cambios

**Resultado esperado**: La galería web podrá cargar videos e imágenes sin errores de CORS en la consola del navegador

---

### 6. Configurar Lifecycle Policies (Opcional - Para control de costos)

**Acción**: Eliminar automáticamente evidencias antiguas después de X meses

**Pasos**:
1. En Settings del bucket, busca **"Lifecycle rules"**
2. Click en **"Add rule"**
3. Configuración ejemplo (eliminar archivos después de 2 años):
   - **Rule name**: `Delete old evidence`
   - **Prefix**: (dejar vacío para aplicar a todo el bucket)
   - **Action**: `Expire current versions`
   - **Days after object creation**: `730` (2 años)
4. Guarda la regla

**Resultado esperado**: Evidencias más antiguas de 2 años se eliminarán automáticamente

**Nota**: Esto es opcional. Puedes configurarlo más adelante cuando tengas claridad sobre tus políticas de retención.

---

## 📋 Resumen de Datos a Proporcionar

Una vez completados los pasos anteriores, proporciona los siguientes datos para configurar la aplicación:

### Datos de Cloudflare R2

| Campo | Valor de Ejemplo | ¿Dónde lo encuentro? |
|-------|------------------|----------------------|
| **Account ID** | `1234567890abcdef1234567890abcdef` | URL del dashboard de Cloudflare |
| **Access Key ID** | `a1b2c3d4e5f6g7h8i9j0` | Pantalla después de crear API Token |
| **Secret Access Key** | `K1L2M3N4O5P6Q7R8S9T0U1V2W3X4Y5Z6` | Pantalla después de crear API Token |
| **Endpoint URL** | `https://1234567890abcdef.r2.cloudflarestorage.com` | Pantalla después de crear API Token |
| **Bucket Name** | `err-evidencias` | Nombre que elegiste al crear el bucket |
| **CDN URL** | `https://cdn.errautomotriz.online` | Dominio personalizado o R2.dev URL |

---

## 🔒 Seguridad

### Mejores Prácticas

1. **No compartas las credenciales**: Tratar el `Secret Access Key` como una contraseña
2. **No las guardes en Git**: Usar `.env` y agregarlo a `.gitignore`
3. **Rotar tokens anualmente**: Crear nuevo token y eliminar el antiguo
4. **Monitorear uso**: Revisar el dashboard de R2 mensualmente para detectar uso anómalo
5. **Alertas de facturación**: Configurar alertas si el almacenamiento supera 50GB inesperadamente

---

## 💰 Estimación de Costos

### Precios de Cloudflare R2 (2026)

- **Almacenamiento**: $0.015 USD por GB/mes
- **Operaciones Clase A** (uploads): $4.50 USD por millón de requests
- **Operaciones Clase B** (downloads): Gratis si usas dominio personalizado con CDN de Cloudflare
- **Egreso de datos**: **Gratis** con CDN de Cloudflare

### Estimación para Taller Automotriz

**Supuestos**:
- 50 órdenes de servicio por mes
- 5 evidencias (fotos/videos) por orden
- Tamaño promedio: 10 MB por archivo
- Retención: 2 años

**Cálculo**:
```
Archivos nuevos por mes: 50 órdenes × 5 evidencias = 250 archivos
Datos nuevos por mes: 250 archivos × 10 MB = 2.5 GB
Datos acumulados en 1 año: 2.5 GB × 12 meses = 30 GB
Datos acumulados en 2 años: 30 GB × 2 = 60 GB

Costo de almacenamiento después de 2 años:
60 GB × $0.015/GB = $0.90 USD/mes

Costo de uploads:
250 uploads/mes × 12 meses = 3,000 uploads/año
3,000 / 1,000,000 × $4.50 = $0.01 USD/año (despreciable)

Total estimado: ~$1 USD/mes después de 2 años de operación
```

**Conclusión**: **Muy económico**. Incluso con el doble de archivos, seguiría siendo menos de $5 USD/mes.

---

## 🆘 Troubleshooting

### Problema: "Access Denied" al subir archivos

**Solución**:
1. Verifica que el API Token tiene permisos de **Object Read & Write**
2. Confirma que el token está asociado al bucket correcto (`err-evidencias`)
3. Revisa que el `Endpoint URL` sea correcto (debe terminar en `.r2.cloudflarestorage.com`)

### Problema: Archivos subidos pero no son accesibles públicamente

**Solución**:
1. Verifica que configuraste el dominio personalizado o habilitaste el R2.dev subdomain
2. Confirma que el archivo existe navegando a: `https://cdn.errautomotriz.online/<path>`
3. Revisa la configuración CORS si los archivos no cargan en la galería web

### Problema: "CORS error" en la galería

**Solución**:
1. Agrega la configuración CORS en el bucket (ver paso 5)
2. Asegúrate de incluir `https://see.errautomotriz.online` en `AllowedOrigins`
3. Limpia caché del navegador y recarga la galería

---

## ✅ Verificación Final

Antes de considerar la configuración completa, verifica lo siguiente:

- [ ] Bucket `err-evidencias` creado
- [ ] Dominio personalizado `cdn.errautomotriz.online` configurado y accesible
- [ ] API Token generado con permisos de Read & Write
- [ ] Access Key ID, Secret Access Key y Endpoint URL guardados de forma segura
- [ ] Account ID identificado
- [ ] Configuración CORS aplicada
- [ ] Prueba de upload manual exitosa (opcional: usar herramienta como Cyberduck o AWS CLI)

---

## 📞 Soporte

Si encuentras algún problema durante la configuración:

1. Revisa la [documentación oficial de Cloudflare R2](https://developers.cloudflare.com/r2/)
2. Consulta los logs del sistema después del primer intento de upload
3. Contacta al equipo de desarrollo con los siguientes datos:
   - Mensaje de error exacto
   - Screenshot del dashboard de R2
   - Configuración actual (sin incluir credenciales secretas)

---

**Última actualización**: 2026-01-09
**Versión del documento**: 1.0
