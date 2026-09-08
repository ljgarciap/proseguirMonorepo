import { test, expect } from '@playwright/test';
import { execSync, execFileSync } from 'child_process';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-339 (Solicitud de Documentos, Crédito Ordinario) — el Director de
 * Crédito, en revision_documental, abre el modal nuevo desde "Solicitar
 * Completar Soportes", re-solicita un documento ya aprobado y agrega uno
 * ad-hoc, con observaciones obligatorias. Verifica que:
 * - El ítem existente vuelve a 'pendiente' y el ad-hoc se crea sin fila en
 *   el catálogo document_requirements (decisión de Luis 2026-09-07).
 * - El crédito pasa a completar_solicitud.
 * - El cliente ve ambos documentos pendientes y la observación en Mis
 *   Cargas (client-upload).
 * - REBOTE (Juan Andrés, 2026-09-08): el mismo re-solicitado también debe
 *   quedar cargable desde Mis Créditos (credito-ordinario.component), no
 *   solo desde Mis Cargas — antes docFileCount() contaba el client_upload_id
 *   conservado como referencia/auditoría y lo mostraba "Cargado", ocultando
 *   el botón "Subir" para el documento que sí tenía un archivo previo.
 *
 * Requiere: docker cp + tinker del seed (ver e2e/README.md), ng serve
 * (localhost:4200) + backend Docker corriendo.
 */

function tinker(php: string): string {
  return execFileSync('docker', ['exec', 'factoring_backend', 'php', 'artisan', 'tinker', '--execute', php]).toString();
}

test.beforeAll(() => {
  execSync('docker cp e2e/fixtures/seed_scrum_339.php factoring_backend:/tmp/seed_scrum_339.php');
  tinker(`require '/tmp/seed_scrum_339.php';`);
});

test('Director de Crédito re-solicita un documento y agrega uno ad-hoc desde el modal', async ({ page }) => {
  const creditoId = tinker(`
    echo \\App\\Models\\CreditoOrdinario::where('numero_solicitud', 'SCRUM339-PW-1')->first()->id;
  `).trim().split('\n').pop();

  const totalRequirementsAntes = parseInt(tinker(`echo \\App\\Models\\DocumentRequirement::count();`).trim().split('\n').pop() || '0', 10);

  // --- 1. Director de Crédito abre el modal y envía la solicitud ---
  await loginAs(page, '1234', '1234', 'coordinador_comercial');
  await page.goto(`/creditos/${creditoId}`);

  await page.getByRole('button', { name: 'Solicitar Completar Soportes' }).click();
  await expect(page.getByRole('heading', { name: 'Solicitud de Documentos' })).toBeVisible({ timeout: 10000 });

  const filaExistente = page.locator('tr', { hasText: 'RUT Playwright 339' });
  await expect(filaExistente).toBeVisible();
  await filaExistente.locator('input[type="checkbox"]').check();

  await page.getByRole('button', { name: 'Agregar documento' }).click();
  await page.getByPlaceholder('Nombre del documento *').fill('Certificación Playwright 339');
  await page.getByPlaceholder('Descripción / instrucción (opcional)').fill('Expedición no mayor a 30 días.');

  await page.locator('textarea').fill('Ajuste requerido: RUT vencido y certificación nueva.');

  await page.getByRole('button', { name: 'Enviar solicitud' }).click();
  await expect(page.getByText('¿Enviar solicitud de documentos?')).toBeVisible({ timeout: 10000 });
  await expect(page.getByText('2 documentos solicitados')).toBeVisible();
  await page.getByRole('button', { name: 'Confirmar envío' }).click();

  await expect(page.getByText('¡Solicitud enviada!')).toBeVisible({ timeout: 10000 });
  await page.getByRole('button', { name: 'OK' }).click();

  // --- 2. Verifica el efecto real en BD (no solo el mensaje de UI) ---
  const totalRequirementsDespues = parseInt(tinker(`echo \\App\\Models\\DocumentRequirement::count();`).trim().split('\n').pop() || '0', 10);
  expect(totalRequirementsDespues).toBe(totalRequirementsAntes); // ad-hoc no toca el catálogo

  const estadoCredito = tinker(`echo \\App\\Models\\CreditoOrdinario::find(${creditoId})->estado;`).trim().split('\n').pop();
  expect(estadoCredito).toBe('completar_solicitud');

  // --- 3. Cliente ve ambos documentos pendientes + la observación ---
  await loginAs(page, '2345', '2345', 'cliente');
  await page.goto('/client-upload');

  await expect(page.getByText('Ajuste requerido: RUT vencido y certificación nueva.')).toBeVisible({ timeout: 10000 });
  await expect(page.locator('.item-row', { hasText: 'RUT Playwright 339' }).getByText('PENDIENTE')).toBeVisible();
  await expect(page.locator('.item-row', { hasText: 'Certificación Playwright 339' }).getByText('PENDIENTE')).toBeVisible();
  await expect(page.getByText('Expedición no mayor a 30 días.')).toBeVisible();

  // --- 4. Rebote: mismo cliente, mismos documentos, desde Mis Créditos ---
  // "RUT Playwright 339" trae un client_upload_id previo (conservado de
  // auditoría) — antes del fix quedaba marcado "Cargado" y sin botón
  // "Subir" acá, aunque en Mis Cargas ya se veía "Pendiente" (arriba).
  await page.goto(`/creditos/${creditoId}`);
  await expect(page.locator('.director-observaciones-box')).toContainText('Ajuste requerido: RUT vencido y certificación nueva.', { timeout: 10000 });

  const boxExistente = page.locator('.doc-box-new', { hasText: 'RUT Playwright 339' });
  await expect(boxExistente).toBeVisible();
  await expect(boxExistente.getByText('Pendiente')).toBeVisible();
  await expect(boxExistente.locator('label.btn-inline-upload')).toBeVisible();

  const boxNuevo = page.locator('.doc-box-new', { hasText: 'Certificación Playwright 339' });
  await expect(boxNuevo).toBeVisible();
  await expect(boxNuevo.getByText('Pendiente')).toBeVisible();
  await expect(boxNuevo.locator('label.btn-inline-upload')).toBeVisible();
});
