# Guía Maestra de Despliegue - Proseguir Factoring

Esta guía documenta el proceso exacto para desplegar el sistema en producción, evitando los errores de cifrado, permisos y conectividad resueltos durante la estabilización.

## 1. Arquitectura de Red (El Triángulo de Comunicación)
Para evitar bloqueos de firewall, todas las conexiones externas pasan por el puerto **80**. Internamente, los servicios se comunican por sus nombres de contenedor:

*   **Frontend -> API:** `http://auto.proseguirliquidez.com/api`
*   **Frontend -> n8n:** `http://auto.proseguirliquidez.com/n8n` (vía túnel Nginx)
*   **Laravel -> n8n:** `http://factoring_n8n:5678` (Red interna Docker)
*   **n8n -> Laravel:** `http://factoring_backend_web/api` (Red interna Docker)

## 2. Preparación de Archivos de Ambiente (.env)
Nunca uses llaves temporales. Los archivos maestros son:
*   **Backend:** `PROD.env` (Contiene la `APP_KEY` fija y clave de DB `Softclass_Fact_2026`).
*   **Frontend:** `frontend/.env.prod` (Contiene las URLs absolutas para evitar errores de construcción en el navegador).

## 3. Proceso de Despliegue "Clean Slate" (Paso a Paso)

Si el servidor tiene "ruido" o errores extraños, ejecuta esta secuencia:

```bash
cd /home/admpsl/apps/proseguir

# 1. Limpieza Total
docker compose down -v
docker system prune -af

# 2. Sincronización de Código
git pull origin master
cp PROD.env .env
cp frontend/.env.prod frontend/.env

# 3. Construcción y Encendido
docker compose up -d --build

# 4. Inicialización de Datos (Laravel)
docker exec factoring_backend php artisan migrate:fresh --seed --force

# 5. Seguridad de Passport (CRUCIAL)
docker exec factoring_backend php artisan passport:install --force
docker exec factoring_backend chmod 600 storage/oauth-private.key
docker exec factoring_backend chmod 600 storage/oauth-public.key

# 6. Permisos de Carpeta
docker exec factoring_backend chmod -R 777 storage bootstrap/cache
```

## 4. Configuración de n8n tras Reset
Al borrar volúmenes, n8n se reinicia. Pasos obligatorios:
1.  Entrar a `http://auto.proseguirliquidez.com:5678/setup`.
2.  Crear cuenta de administrador.
3.  Importar el archivo `workflow/FactoringApiDocker.json`.
4.  **Activar (Switch ON)** el flujo para que la URL de producción no devuelva 404.

# 6. Módulo Conciliación Susuerte (REQUERIDO)
Para que el procesamiento de PDFs funcione en el servidor, ejecuta:
```bash
# Instalar dependencias de Node en el backend
docker exec factoring_backend npm install pdfreader

# Recrear el enlace de storage de forma relativa (Evita Error 403)
docker exec factoring_backend rm -f public/storage
docker exec factoring_backend ln -s ../storage/app/public public/storage
```

## 7. Lecciones Aprendidas (Evitar errores recurrentes)
*   **Error 403 (Descarga):** Causado por enlaces simbólicos con rutas absolutas locales. Solución: Usar rutas relativas en el comando `ln -s`.
*   **Error 502/500 (Conciliación):** Causado por la falta de Node.js o `pdfreader` en el contenedor de backend.
*   **Error ESM (Node):** Los archivos `.js` en el backend se tratan como módulos ES. Solución: El ayudante de PDF debe llamarse `extract_pdf.cjs`.
