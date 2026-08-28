import { test, expect } from '@playwright/test';
import { execSync, execFileSync } from 'child_process';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-292 (rebote, reportado por Juan Andrés tras el primer fix del
 * ticket) — al subir un documento de Etapa 4/5 (Formalización de
 * Garantías) directamente desde la pantalla de Crédito Ordinario (no desde
 * "Solicitud de Documentos"), el mismo archivo aparecía listado 2 veces:
 * una vez desde credito.documentos[campoDoc] (getDocFiles, escrito
 * directo por transition()) y otra desde el DocumentRequestItem.upload
 * (doc.upload) — mismo bug que SCRUM-256 ya había resuelto para Etapa 1
 * pero nunca se replicó en Etapa 4. El contador "Cargado (N)" ya
 * deduplicaba correctamente (docFileCount) — solo el listado de
 * enlaces/botones mostraba el duplicado.
 *
 * Reusa el fixture de SCRUM-193/205 (GC193205-PW-1), igual que
 * scrum-229-documentos-etapa4-credito-ordinario.spec.ts, pero sube el
 * archivo desde Crédito Ordinario en vez de "Solicitud de Documentos".
 *
 * Requiere: docker cp + tinker del seed, ng serve (localhost:4200) +
 * backend Docker corriendo.
 */

function tinker(php: string): string {
  return execFileSync('docker', ['exec', 'factoring_backend', 'php', 'artisan', 'tinker', '--execute', php]).toString();
}

test.beforeAll(() => {
  execSync('docker cp e2e/fixtures/seed_scrum_193_205.php factoring_backend:/tmp/seed_scrum_193_205.php');
  tinker(`require '/tmp/seed_scrum_193_205.php';`);
});

test('subir un documento de Etapa 4 desde Crédito Ordinario no lo duplica en el listado', async ({ page }) => {
  // --- 1. Coordinador notifica pidiendo el preset de garantías ---
  await loginAs(page, '1234', '1234', 'coordinador_comercial');
  await page.goto('/gestion-creditos');
  const filaInicial = page.locator('tr', { hasText: 'GC193205-PW-1' });
  await expect(filaInicial).toBeVisible({ timeout: 10000 });
  await filaInicial.getByRole('button', { name: /Gestionar/i }).click();
  await expect(page).toHaveURL(/\/gestion-creditos\/\d+$/);

  await page.locator('input[type="email"]').fill('cliente@test.com');
  await page.getByPlaceholder('Asunto del correo').fill('Aprobación de garantías');
  await page.getByPlaceholder('Mensaje que verá el cliente').fill('Por favor diligencie las garantías solicitadas.');
  await page.locator('.form-group', { hasText: 'Documentación Requerida' }).locator('select').selectOption({ label: 'Preset Playwright 193-205' });

  await page.getByRole('button', { name: /Registrar y enviar notificación/i }).click();
  await page.getByRole('button', { name: 'Sí, enviar' }).click();
  await expect(page.getByText('¡Listo!')).toBeVisible({ timeout: 10000 });
  await page.getByRole('button', { name: 'OK' }).click();
  await expect(page).toHaveURL(/\/gestion-creditos$/, { timeout: 10000 });

  // --- 2. Cliente sube el documento directo desde Crédito Ordinario, Etapa 4 (no desde "Solicitud de Documentos") ---
  const creditoId = tinker(`
    echo \\App\\Models\\CreditoOrdinario::where('numero_solicitud', 'GC193205-PW-1')->first()->id;
  `).trim().split('\n').pop();

  await loginAs(page, '2345', '2345', 'cliente');
  await page.goto(`/creditos/${creditoId}`);
  await expect(page.getByText('Documentación solicitada:')).toBeVisible({ timeout: 10000 });

  const docBox = page.locator('.doc-box-new', { hasText: 'Pagaré Playwright 193-205' });
  await expect(docBox).toBeVisible({ timeout: 10000 });

  await docBox.locator('input[type="file"]').setInputFiles({
    name: 'pagare-292.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from('%PDF-1.4 contenido de prueba Playwright SCRUM-292'),
  });

  // executeTransition() pide confirmación (SweetAlert2) antes de disparar
  // la subida real, y confirma con un segundo modal "¡Procesado!" al
  // terminar.
  await expect(page.getByText('Confirmar Acción')).toBeVisible({ timeout: 5000 });
  await page.getByRole('button', { name: 'Confirmar y Avanzar' }).click();
  await expect(page.getByText('¡Procesado!')).toBeVisible({ timeout: 10000 });
  await page.getByRole('button', { name: 'OK' }).click();

  // El contador ya deduplicaba antes del fix — la aserción clave es que
  // solo aparece UN enlace/botón visible para el archivo, no dos.
  await expect(docBox.getByText(/Cargado \(1\)/)).toBeVisible({ timeout: 15000 });
  await expect(docBox.getByRole('link', { name: /pagare-292\.pdf/i }).or(docBox.getByRole('button', { name: /pagare-292\.pdf/i }))).toHaveCount(1);
});
