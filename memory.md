# Estado del Proyecto y Memoria (Proseguir)

## 📌 Progreso Actual (SCRUM-46 y Ajustes)
- **Git:** Todo el código está comiteado y empujado (`pushed`) a la rama `scrum-ajustes-varios`. Tu árbol de trabajo está limpio y seguro.
- **Frontend:**
  - Se implementaron los contadores dinámicos (badges) en el menú lateral para mostrar mandatos y asignaciones pendientes.
  - Se corrigió el problema de caché persistente agregando reglas al `nginx.conf` (`Cache-Control: no-store, no-cache` para el `index.html`).
- **Backend / Base de Datos:**
  - La base de datos local fue regenerada y **poblada con datos de prueba** (`php artisan db:seed`).
  - Se creó correctamente el cliente de acceso personal de Passport (`php artisan passport:client --personal`), solucionando el error HTTP 500 en el login.
  - **Credenciales de prueba verificadas:** Usuario: `1234` / Contraseña: `1234` (Super Administrador).

## 🐛 Problema Pendiente (Glitch de Chrome)
- **Síntoma:** El navegador Chrome (en su ventana normal) se queda en una pantalla azul sólida al acceder a `http://localhost`, sin mostrar errores en consola y cargando aparentemente los scripts pero sin renderizar Angular.
- **Diagnóstico:** Dado que el sistema funciona perfectamente en modo Incógnito y a través de la IP `http://127.0.0.1`, se ha confirmado que el problema es estrictamente un bloqueo interno, extensión, caché corrupto o regla HSTS del perfil principal de Chrome asociado a la palabra `localhost`.
- **Acción actual:** El usuario está reiniciando el equipo/navegador para intentar purgar el estado atascado de Chrome.

## 🚀 Siguientes Pasos (Al volver)
1. **Verificar el reinicio:** Comprobar si Chrome normal ya permite acceder a `http://localhost`. Si no, se recomienda continuar el desarrollo usando `http://127.0.0.1` o modo incógnito para no bloquear el avance.
2. **Revisar SCRUM-46:** Entrar a la plataforma y validar visualmente los badges en el menú lateral.
3. **Continuar el desarrollo:** Definir y comenzar el siguiente requerimiento o historia de usuario del backlog.
