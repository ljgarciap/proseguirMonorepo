# Estado del Proyecto y Memoria (Proseguir)

## 📌 Progreso Actual (Roadmap y Correcciones)
- **Git:** El árbol de trabajo local está listo para subir los cambios correspondientes a la implementación del Roadmap del Superadmin, la corrección de la migración de créditos y las optimizaciones de regeneración de Passport.
- **Frontend:**
  - Se implementó la nueva sección **Planificación > Roadmap del Sistema** (`/roadmap`) exclusiva para el rol `superadmin`. Muestra tarjetas de todas las funcionalidades de la plataforma y su matriz general de permisos.
- **Backend / Base de Datos:**
  - **Corrección de Migración de Créditos:** Se corrigió el nombre del método en `2026_05_30_100000_create_credito_ordinarios_table.php` de `run()` a `up()`. La base de datos local fue regenerada completamente con éxito (`migrate:fresh --seed`).
  - **Corrección de DbCleaner (Passport):** Registrado `Laravel\Passport\PassportServiceProvider::class` en `bootstrap/providers.php` y agregada la llamada explícita no interactiva a `passport:client --personal` en `DbCleanerController.php` para evitar errores 500 al resetear el sistema desde la UI.
