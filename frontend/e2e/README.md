# E2E (Playwright)

Playwright CLI es la única herramienta permitida para validar UX/UI en navegador real en este
workspace (regla dura, ver `CLAUDE.md` raíz) — nunca `claude-in-chrome`.

## Requisitos antes de correr

```bash
# 1. Backend + BD (desde la raíz del repo)
cd .. && docker compose up -d

# 2. Frontend en modo dev (puerto 4200, apunta a localhost:8000 vía environment.ts)
cd frontend && npm start
```

`playwright.config.ts` no auto-arranca ninguno de los dos — deben estar corriendo antes de
`npx playwright test`.

## Correr los tests

```bash
npx playwright test                      # headless, todos los specs
npx playwright test --project=chromium   # explícito (es el único proyecto configurado hoy)
npx playwright test --grep "SARLAFT"     # un caso puntual
```

Reportes/trazas de fallos quedan en `test-results/` (gitignored).

## Login / cambio de rol (`e2e/helpers/auth.ts`)

- El login es por **Número de Documento** (no email) + contraseña. Superadmin de pruebas:
  doc `1234` / pass `1234` (todos los roles).
- Si el usuario autenticado tiene más de un rol, aparece la pantalla "Selecciona un Perfil"
  (botones, no dropdown) — `loginAs()` la maneja sola.
- ⚠️ `Locator.isVisible({ timeout })` en Playwright **no espera** — consulta el DOM en el
  instante y listo, ignorando `timeout` (a diferencia de `expect(locator).toBeVisible()`). Para
  esperar la aparición de algo antes de decidir una rama (ej. "¿aparece el picker de roles o
  no?"), usar `locator.waitFor({ state: 'visible', timeout })` envuelto en try/catch, nunca
  `isVisible({ timeout })`. Bug real encontrado armando esta suite (SCRUM-178,
  2026-08-05): el login quedaba pegado en `/login` sin que ningún test lo explicara, porque el
  chequeo de "¿está el picker?" devolvía `false` de inmediato, antes de que Angular lo renderizara.
- Cambiar de rol activo ya logueado: `page.locator('.test-role-switcher select').selectOption(rol)`
  — el `value` de cada `<option>` es el string crudo del rol (`coordinador_comercial`, no
  "Coordinador Comercial").
- Confirmaciones son SweetAlert2 (no dialogs nativos) — se clickean como cualquier botón
  (`page.getByRole('button', { name: 'Sí, enviar' })`).

## Datos de prueba

`gestion-creditos.spec.ts` corre contra 4 `CreditoOrdinario` sembrados con el prefijo `GC-PW-`
(uno por resultado: Aprobada, SARLAFT desfavorable, Rechazada por Comité, Pendiente por Comité),
vía `e2e/fixtures/seed_gestion_creditos.php`:

```bash
docker cp e2e/fixtures/seed_gestion_creditos.php factoring_backend:/tmp/seed_gestion_creditos.php
docker exec factoring_backend php artisan tinker --execute="require '/tmp/seed_gestion_creditos.php';"
```

El script es idempotente respecto a nombres (`GC-PW-APR/SAR/REC/PEN`): si ya existen, los omite en
vez de duplicarlos — para resembrar desde cero hay que borrarlos primero (`CreditoOrdinario`,
`SolicitudCredito` y cualquier `DocumentRequest` de prueba asociado).

**La suite no es idempotente**: una vez un test "gestiona" una solicitud (envía la notificación),
el registro deja de estar disponible para gestionar de nuevo (o sale de la bandeja, si pasó a
`formalizacion_garantias`). Para volver a correr la suite completa desde cero hay que
borrar y resembrar los `GC-PW-*` antes.
