import { test, expect } from '@playwright/test';
import { execSync, execFileSync } from 'child_process';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-229 (regresión reportada por Juan tras el primer fix, commit
 * d1236fe) — la Etapa 4 (Formalización de Garantías) de Crédito Ordinario
 * ya mostraba el preset solicitado por el Coordinador Comercial, pero NO el
 * archivo que el cliente cargó desde la pantalla de "Solicitud de
 * Documentos" (la que habilita Gestión de Créditos al notificar con un
 * preset de garantías) — porque ese upload escribe en
 * DocumentRequestItem.client_upload_id, no en CreditoOrdinario.documentos
 * (que es lo único que leía la pantalla). Reusa el fixture de
 * SCRUM-193/205 (GC193205-PW-1) hasta el punto donde el cliente ya
 * diligenció la garantía, y valida que el archivo aparezca descargable en
 * Crédito Ordinario, Etapa 4.
 *
 * Requiere: docker cp + tinker del seed (ver comentario del spec 193-205),
 * ng serve (localhost:4200) + backend Docker corriendo.
 */

function tinker(php: string): string {
  return execFileSync('docker', ['exec', 'factoring_backend', 'php', 'artisan', 'tinker', '--execute', php]).toString();
}

test.beforeAll(() => {
  execSync('docker cp e2e/fixtures/seed_scrum_193_205.php factoring_backend:/tmp/seed_scrum_193_205.php');
  tinker(`require '/tmp/seed_scrum_193_205.php';`);
});

test('documento cargado desde Solicitud de Documentos se ve y descarga en Crédito Ordinario Etapa 4', async ({ page }) => {
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

  // --- 2. Cliente diligencia la garantía del preset (vía Solicitud de Documentos) ---
  await loginAs(page, '2345', '2345', 'cliente');
  await page.goto('/client-upload');
  await expect(page.getByText('Pagaré Playwright 193-205')).toBeVisible({ timeout: 10000 });

  await page.locator('.item-row', { hasText: 'Pagaré Playwright 193-205' })
    .locator('input[type="file"]')
    .setInputFiles({
      name: 'pagare-229.pdf',
      mimeType: 'application/pdf',
      buffer: Buffer.from('%PDF-1.4 contenido de prueba Playwright SCRUM-229'),
    });
  await expect(page.locator('.item-row', { hasText: 'Pagaré Playwright 193-205' }).getByText('SUBIDO')).toBeVisible({ timeout: 15000 });

  // --- 3. Verifica en Crédito Ordinario, Etapa 4, que el archivo se ve y descarga ---
  const creditoId = tinker(`
    echo \\App\\Models\\CreditoOrdinario::where('numero_solicitud', 'GC193205-PW-1')->first()->id;
  `).trim().split('\n').pop();

  await page.goto(`/creditos/${creditoId}`);
  await expect(page.getByText('Documentación solicitada:')).toBeVisible({ timeout: 10000 });
  await expect(page.getByText('Preset Playwright 193-205')).toBeVisible();

  const docBox = page.locator('.doc-box-new', { hasText: 'Pagaré Playwright 193-205' });
  await expect(docBox).toBeVisible({ timeout: 10000 });
  await expect(docBox.getByText(/Cargado \(1\)/)).toBeVisible();

  const downloadBtn = docBox.getByRole('button', { name: /pagare-229\.pdf/i });
  await expect(downloadBtn).toBeVisible();

  // Según el Content-Type que detecte el backend para el archivo, el
  // componente abre una pestaña nueva (PDF/imagen) o dispara una descarga
  // — cualquiera de las dos confirma que el archivo real se resolvió
  // (antes del fix, el click no tenía ningún archivo real que abrir).
  const opened = Promise.race([
    page.waitForEvent('popup', { timeout: 8000 }).then(() => 'popup'),
    page.waitForEvent('download', { timeout: 8000 }).then(() => 'download'),
  ]);
  await downloadBtn.click();
  await expect(opened).resolves.toMatch(/popup|download/);
  await expect(page.getByText('No se pudo abrir el documento.')).not.toBeVisible();
});
