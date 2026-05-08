# 🚀 Guía de Despliegue - Monorepo Proseguir

Esta guía detalla los pasos para actualizar el sistema en el servidor de producción.

## 1. En tu Máquina Local
Siempre asegúrate de que todo esté en GitHub antes de ir al servidor.

```bash
# Ir a la raíz del proyecto
cd /ruta/hacia/Proseguir

# Subir cambios
git add .
git commit -m "Descripción de tus cambios"
git push origin master
```

## 2. En el Servidor (SSH)
Entra a la carpeta del proyecto y sincroniza.

```bash
cd /home/admpsl/apps/proseguir

# Bajar lo nuevo
git pull origin master

# Reconstruir contenedores (solo si cambiaste Dockerfiles o el docker-compose)
# Si solo cambiaste código PHP/Angular, un restart suele bastar, 
# pero el 'up --build' es lo más seguro:
docker compose up -d --build
```

## 3. Mantenimiento del Backend (IMPORTANTE)
Si añadiste tablas nuevas, librerías o cambiaste el `.env`, corre estos comandos:

```bash
# A. Instalar nuevas librerías (si añadiste paquetes)
docker exec -it factoring_backend composer install --no-dev --optimize-autoloader

# B. Actualizar Base de Datos (si hay migraciones nuevas)
docker exec -it factoring_backend php artisan migrate --force

# C. Limpiar Caché (siempre recomendado tras actualizar)
docker exec -it factoring_backend php artisan config:cache
docker exec -it factoring_backend php artisan route:cache
```

---

## 🛠 Solución de Problemas Comunes

### Error 500 al iniciar sesión
Suele ser un problema de permisos o de falta de llaves de la API.
```bash
docker exec -it factoring_backend php artisan passport:install --force
docker exec -it factoring_backend chown -R www-data:www-data storage/
```

### El Frontend no conecta a la API (CORS)
Asegúrate de que el archivo `frontend/set-env.js` tenga `/api` como ruta relativa por defecto. Si necesitas cambiar la URL, hazlo en el `.env` del frontend local antes de subir.

### Acceso a Herramientas
- **App**: [http://auto.proseguirliquidez.com](http://auto.proseguirliquidez.com)
- **API**: [http://auto.proseguirliquidez.com:8000](http://auto.proseguirliquidez.com:8000)
- **n8n**: [http://auto.proseguirliquidez.com/n8n](http://auto.proseguirliquidez.com/n8n)
