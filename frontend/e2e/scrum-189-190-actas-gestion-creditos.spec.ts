import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * Validación visual SCRUM-189 (Actas Comité de Crédito) + SCRUM-190
 * (Gestión de Créditos), 2026-08-12. Requiere haber corrido antes:
 *
 *   docker cp e2e/fixtures/seed_actas_comite_190.php factoring_backend:/tmp/seed_actas_comite_190.php
 *   docker exec factoring_backend php artisan tinker --execute="require '/tmp/seed_actas_comite_190.php';"
 *
 * Cubre: previsualizar sin 500 (punto 5), buscador de cliente + dropdown de
 * tipo + separador de miles en "Agregar solicitud manual" (punto 2),
 * identificación + dropdown de amortización en Decisión (punto 3/4),
 * prefill de monto_decision en Resumen (punto 4), y materialización de la
 * solicitud manual visible en Gestión de Créditos (SCRUM-190.1).
 */
test('flujo completo Acta de Comité con solicitud manual, visible luego en Gestión de Créditos', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'coordinador_comercial');

  await page.goto('/actas-comite');
  await page.getByRole('button', { name: /Generar acta pendiente/i }).click();
  await page.getByRole('button', { name: 'OK' }).click(); // SweetAlert2 "Acta generada"

  // Navega sola al detalle recién generado (routing SPA sin full-reload —
  // waitForURL con su waitUntil:'load' por defecto no siempre lo detecta).
  await expect.poll(() => page.url(), { timeout: 10000 }).toMatch(/\/actas-comite\/\d+$/);

  // --- Desarrollo: el crédito de sistema sembrado aparece listado ---
  await page.getByRole('button', { name: '2. Desarrollo' }).click();
  await expect(page.getByText('Cliente Playwright Sistema')).toBeVisible();

  // --- Punto 2: buscador de cliente + dropdown de tipo + separador de miles ---
  await page.locator('.cliente-autocomplete input').fill('Cliente Playwright Manual');
  await page.getByText(/Cliente Playwright Manual.*900333444/).click();
  // El placeholder ganó un "*" (campo obligatorio) en el fix del mismo
  // SCRUM-189 sobre el mensaje de "Datos incompletos" (2026-08-13) — el
  // locator exacto quedó desactualizado, no es un bug funcional.
  await expect(page.locator('.inline-add-solicitud input[placeholder="Cliente *"]')).toHaveValue('Cliente Playwright Manual');
  await expect(page.locator('.inline-add-solicitud input[placeholder="N.° de identificación"]')).toHaveValue('900333444');

  const tipoSelect = page.locator('.inline-add-solicitud select');
  await expect(tipoSelect.locator('option')).toContainText(['Crédito Ordinario']);
  await tipoSelect.selectOption({ label: 'Crédito Ordinario' });

  const montoInput = page.locator('.inline-add-solicitud input[inputmode="decimal"]');
  await montoInput.fill('12000000');
  await montoInput.blur();
  await expect(montoInput).toHaveValue('12.000.000');

  await page.getByRole('button', { name: 'Agregar solicitud manual' }).click();
  await expect(page.getByText('Manual').first()).toBeVisible();

  // --- Completar el resto del acta ANTES de decidir las solicitudes: cada
  // autoguardado (PUT /actas-comite/{id}) vuelve a correr
  // sincronizarSolicitudesElegibles() (SCRUM-189.1) — si el entorno dev
  // tiene otros CreditoOrdinario llegando a comite_evaluacion en paralelo
  // (cola/otra sesión), pueden sumarse tarjetas nuevas a mitad de camino.
  // Decidir todo DESPUÉS de este bloque evita dejar alguna sin decisión. ---
  await page.getByRole('button', { name: '1. Orden del día' }).click();
  await page.locator('input[type="date"]').fill('2026-08-12');
  await page.locator('input[type="time"]').first().fill('09:00');
  const editorLugar = page.locator('.ql-editor').first();
  await editorLugar.click();
  await editorLugar.fill('Sala de juntas Playwright');
  await page.locator('input[placeholder="Nombre del asistente"]').fill('Juan Pérez Playwright');
  await page.getByRole('button', { name: 'Agregar', exact: true }).first().click();
  await page.getByRole('button', { name: /Aprobar orden del día/i }).click();
  await page.getByRole('button', { name: 'OK' }).click(); // SweetAlert2 "Orden del día aprobado"

  await page.getByRole('button', { name: '5. Observaciones' }).click();
  await page.locator('input[type="time"]').fill('10:30');

  await page.getByRole('button', { name: '6. Firmantes' }).click();
  // .first(): el acta prellena "Asistentes" con los de la última acta
  // Aprobada (comportamiento esperado) — en corridas repetidas de este
  // spec puede haber más de un candidato con el mismo nombre.
  await page.getByRole('button', { name: /\+ Juan Pérez Playwright/ }).first().click();

  // --- Punto 3/4: Decisión — identificación visible + dropdown de
  // amortización. Último checkpoint antes de registrar: se decide TODA
  // tarjeta presente, sin fijar un count exacto (ver nota arriba). ---
  await page.getByRole('button', { name: '3. Decisión' }).click();
  await expect(page.getByText('900333444')).toBeVisible(); // identificación del manual, ya autocompletada
  await expect(page.getByText('900222333')).toBeVisible(); // identificación del de sistema

  const cards = page.locator('.solicitud-detalle-card');
  // Los <label> no están asociados por for/id — se ubica el input/select por
  // el .form-field contenedor en vez de getByLabel().
  const campo = (card: typeof cards, etiqueta: string) => card.locator('.form-field', { hasText: etiqueta });

  const cardManual = cards.filter({ hasText: 'Cliente Playwright Manual' });
  await campo(cardManual, 'Amortización').locator('select').selectOption({ label: 'Mensual' });
  await campo(cardManual, 'N.° de identificación').locator('input').fill('900333444');
  await campo(cardManual, 'Plazo (meses)').locator('input').fill('24');
  await campo(cardManual, 'Fuente de pago').locator('input').fill('Ingresos operacionales');
  await campo(cardManual, 'Estado').locator('select').selectOption('aprobado');

  const cardSistema = cards.filter({ hasText: 'Cliente Playwright Sistema' });
  await campo(cardSistema, 'Amortización').locator('select').selectOption({ label: 'Mensual' });
  await campo(cardSistema, 'Estado').locator('select').selectOption('rechazado');

  // Cualquier otra tarjeta ajena a este seed (auto-sync trayendo créditos de
  // otras sesiones) también necesita una decisión, o registrar() bloquea con
  // "Decisión pendiente" — se marca Pendiente por Comité, la más neutra (no
  // materializa ni transiciona nada, ver RESULTADOS/mapaDecision).
  const decisionesValidas = ['aprobado', 'rechazado', 'pendiente'];
  const totalCards = await cards.count();
  for (let i = 0; i < totalCards; i++) {
    const card = cards.nth(i);
    const estadoSelect = card.locator('.form-field', { hasText: 'Estado' }).locator('select');
    // El <option [ngValue]="null">Seleccione...</option> no serializa como
    // "" — Angular lo renderiza como "0: null" (marcador interno de
    // [ngValue] sin trackBy) — se detecta "sin decidir" por exclusión de
    // los 3 valores reales en vez de por string vacío.
    if (!decisionesValidas.includes(await estadoSelect.inputValue())) {
      await estadoSelect.selectOption('pendiente');
    }
  }

  // --- Punto 4: Resumen — monto_decision ya viene prellenado ---
  await page.getByRole('button', { name: '4. Resumen' }).click();
  const montoDecisionInputs = page.locator('table.modern-table input[inputmode="decimal"]');
  await expect(montoDecisionInputs.first()).not.toHaveValue('');

  // Previsualizar/Descargar/Registrar viven en la pestaña Firmantes.
  await page.getByRole('button', { name: '6. Firmantes' }).click();

  // --- Punto 5: Previsualizar no debe dar 500 (antes: <a href> sin auth →
  // Auth::user() null → 500 en blanco, ver ActaComiteController). Se
  // verifica el status HTTP real de la request en vez de la URL del popup:
  // Playwright/Chromium no trackea de forma confiable una navegación a
  // blob: en una pestaña nueva (el popup se queda reportando about:blank
  // aunque el usuario real sí vea el PDF — comprobado con logging manual).
  const respuestaPrevisualizar = page.waitForResponse(resp => resp.url().includes('/previsualizar'));
  const [popup] = await Promise.all([
    page.waitForEvent('popup'),
    page.getByRole('button', { name: /Previsualizar acta/i }).click(),
  ]);
  const resp = await respuestaPrevisualizar;
  expect(resp.status()).toBe(200);
  expect(resp.headers()['content-type']).toContain('application/pdf');
  await expect(page.locator('.swal2-popup')).toHaveCount(0); // sin error en la página principal
  await popup.close();

  await page.getByRole('button', { name: /Registrar acta/i }).click();
  await page.getByRole('button', { name: 'Registrar acta' }).click(); // confirmación SweetAlert2
  await expect(page.getByText('El acta fue registrada correctamente')).toBeVisible({ timeout: 10000 });

  // --- SCRUM-190.1: la solicitud manual materializó un CreditoOrdinario
  // real, visible en Gestión de Créditos igual que uno normal ---
  await page.goto('/gestion-creditos');
  // .first(): en corridas repetidas de este spec, cada una materializa su
  // propio CreditoOrdinario nuevo para el mismo Cliente (comportamiento
  // correcto — cada solicitud manual de cada acta es independiente).
  await expect(page.getByText('Cliente Playwright Manual').first()).toBeVisible({ timeout: 10000 });
});
