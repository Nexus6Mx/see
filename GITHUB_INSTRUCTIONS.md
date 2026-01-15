# 📋 Instrucciones para Subir a GitHub

## 🚀 Repositorio listo y commiteado

El repositorio Git ya está inicializado con todo el código.

---

## 📤 Pasos para subir a GitHub:

### 1. Crear repositorio en GitHub

1. Ve a https://github.com/new
2. Nombre del repo: `see-sistema-evidencias` (o como prefieras)
3. **NO inicialices** con README, .gitignore ni LICENSE
4. Click en **"Create repository"**

### 2. Conectar y subir el código

Copia y ejecuta estos comandos (GitHub te los mostrará):

```bash
cd /home/nexus6/devs/see

# Agregar remote
git remote add origin https://github.com/TU_USUARIO/NOMBRE_DEL_REPO.git

# Subir código
git push -u origin main
```

**Nota:** Reemplaza `TU_USUARIO` y `NOMBRE_DEL_REPO` con tus datos.

---

## 🔐 Autenticación

GitHub pedirá autenticación. Opciones:

### Opción A: Personal Access Token (Recomendado)

1. Ve a GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Generate new token
3. Marca: `repo` (full control)
4. Copia el token
5. Úsalo como contraseña cuando Git te pida

### Opción B: SSH Keys

```bash
# Generar SSH key
ssh-keygen -t ed25519 -C "tu_email@example.com"

# Copiar clave pública
cat ~/.ssh/id_ed25519.pub

# Pegarla en GitHub → Settings → SSH Keys
```

Luego cambia el remote a SSH:
```bash
git remote set-url origin git@github.com:TU_USUARIO/NOMBRE_DEL_REPO.git
git push -u origin main
```

---

## 📝 Después del push

Una vez subido, tu repositorio estará en:
```
https://github.com/TU_USUARIO/NOMBRE_DEL_REPO
```

### Archivos que se subirán:
- ✅ Todo el código fuente
- ✅ Documentación completa
- ✅ TODO.md con pendientes
- ✅ Configuraciones (sin credenciales sensibles)

### Archivos que NO se subirán (por .gitignore):
- ❌ `.env` (credenciales)
- ❌ `vendor/` (dependencias, se instalan con composer)
- ❌ `logs/` (archivos de log)
- ❌ `temp/` (archivos temporales)

---

## ⚠️ IMPORTANTE - Seguridad

El archivo `.env.production` **SÍ se subió** porque contiene las credenciales de ejemplo.

**NUNCA subas el archivo `.env` real con credenciales de producción.**

Para futuras actualizaciones:
```bash
# Asegúrate que .env esté en .gitignore
echo ".env" >> .gitignore

# Ver qué archivos se subirán antes de commit
git status
```

---

## 🔄 Para futuras actualizaciones:

```bash
# Ver cambios
git status

# Agregar cambios
git add .

# Commit
git commit -m "Descripción de cambios"

# Subir a GitHub
git push
```

---

## 📊 Estado del Proyecto

**✅ Sistema en producción:** https://see.errautomotriz.online
**✅ Código listo para GitHub**
**📋 TODO creado:** Ver `TODO.md` para pendientes

---

¡Listo para subir! 🚀
