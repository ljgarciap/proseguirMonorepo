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

## SCRUM-189/190 — Actas Comité de Crédito (`scrum-189-190-actas-gestion-creditos.spec.ts`)

```bash
docker cp e2e/fixtures/seed_actas_comite_190.php factoring_backend:/tmp/seed_actas_comite_190.php
docker exec factoring_backend php artisan tinker --execute="require '/tmp/seed_actas_comite_190.php';"
```

Siembra un `CreditoOrdinario` (`AC-PW-1`) en `comite_evaluacion` y un `Cliente` ("Cliente
Playwright Manual") para probar el buscador de la solicitud manual. El script también:
- Limpia cualquier acta `pendiente`/`borrador` que haya quedado de una corrida anterior
  interrumpida (bloquea "Generar acta pendiente" mientras exista una).
- Resetea `AC-PW-1` a `comite_evaluacion` si una corrida previa ya lo decidió — correr el fixture
  antes de CADA corrida del spec, no solo la primera vez.

Cada corrida exitosa **registra una acta nueva** (consecutivo `numero`) y **materializa un
`CreditoOrdinario` nuevo** para "Cliente Playwright Manual" (SCRUM-190.1, comportamiento
correcto — no es un fixture que se reutiliza, cada acta es independiente). Limpiar después de
validar si no se quiere acumular ruido en Gestión de Créditos:

```php
// vía tinker, borra los créditos/solicitudes/actas creados por corridas de validación
// (ver el bloque de limpieza usado en la sesión 2026-08-12, no versionado como script separado)
```

El entorno dev puede tener otros `CreditoOrdinario` en `comite_evaluacion` ajenos a este fixture
(de otras sesiones de QA) — el spec no fija un número exacto de tarjetas en la pestaña Decisión,
decide cualquiera que aparezca (auto-sync, SCRUM-189.1) como "Pendiente por Comité" para no
bloquear el registro del acta.

## SCRUM-191 — Gestión de Créditos: documentos + acceso al Acta (`scrum-191-gestion-creditos-documentos.spec.ts`)

```bash
docker cp e2e/fixtures/seed_scrum_191.php factoring_backend:/tmp/seed_scrum_191.php
docker exec factoring_backend php artisan tinker --execute="require '/tmp/seed_scrum_191.php';"
```

Siembra 3 `CreditoOrdinario` (`GC191-PW-SIN-DOCS`, `GC191-PW-CON-DOCS`, `GC191-PW-ACTA`) en
`pendiente_comite`/con Acta ya firmada, más el `Cliente` "Cliente Playwright 191" — vinculado al
usuario de portal `cliente` ya sembrado por `UserSeeder` (doc `2345` / pass `2345`). El script es
idempotente: resetea los 2 primeros créditos a `pendiente_comite` (y borra cualquier
`DocumentRequest` previo del segundo) en cada corrida — correrlo antes de CADA ejecución del spec.

3 tests independientes:
1. Notificar `pendiente_comite` con "requiere documentos: No" → el crédito desaparece de la
   bandeja (volvió solo a `comite_evaluacion`).
2. Notificar con "requiere documentos: Sí" → login como cliente (`/client-upload`) → recarga el
   documento del preset → login como coordinador → aprueba desde el panel "Documentos reenviados
   por el cliente" → el crédito vuelve a `comite_evaluacion`.
3. Login como cliente → `/creditos` → confirma que el link "Ver" del Acta de Comité Firmada y el
   panel legacy dirigido al Comité no aparecen para ese rol.

Encontró un bug real durante la validación (no del test): al aprobar un documento sin motivo de
rechazo, SweetAlert2 devuelve el booleano `true` como `result.value` (no hay `input` configurado)
— viajaba tal cual como `observaciones` y el backend lo rechazaba (`"must be a string"`). Fix en
`gestion-creditos-detalle.component.ts::revisarItem()`.

Limpieza tras validar (evita acumular ruido en Gestión de Créditos):

```php
// vía tinker, borra los 3 CreditoOrdinario/SolicitudCredito/DocumentRequest GC191-PW-* y el
// ClientUpload de prueba (ver bloque de limpieza usado en la sesión 2026-08-12)
```
