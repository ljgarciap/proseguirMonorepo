import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * Validación visual SCRUM-191 (Gestión de Créditos), 2026-08-12. Requiere
 * haber corrido antes (el fixture resetea los 3 créditos GC191-PW-* a su
 * estado inicial en cada corrida, así que se puede re-ejecutar sin limpiar
 * nada a mano):
 *
 *   docker cp e2e/fixtures/seed_scrum_191.php factoring_backend:/tmp/seed_scrum_191.php
 *   docker exec factoring_backend php artisan tinker --execute="require '/tmp/seed_scrum_191.php';"
 */

test('pendiente_comite sin requerir documentos vuelve directo a comite_evaluacion', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'coordinador_comercial');

  await page.goto('/gestion-creditos');
  const fila = page.locator('tr', { hasText: 'GC191-PW-SIN-DOCS' });
  await expect(fila).toBeVisible({ timeout: 10000 });
  await fila.getByRole('button', { name: /Gestionar/i }).click();
  await expect(page).toHaveURL(/\/gestion-creditos\/\d+$/);

  await page.locator('input[type="email"]').fill('cliente@test.com');
  await page.getByPlaceholder('Asunto del correo').fill('Solicitud aplazada por el Comité');
  await page.getByPlaceholder('Mensaje que verá el cliente').fill('El Comité aplazó la decisión de su solicitud.');
  await page.locator('.radio-option', { hasText: 'No' }).click(); // requiere_documentos = No

  await page.getByRole('button', { name: /Registrar y enviar notificación/i }).click();
  await page.getByRole('button', { name: 'Sí, enviar' }).click();
  await expect(page.getByText('¡Listo!')).toBeVisible({ timeout: 10000 });
  await page.getByRole('button', { name: 'OK' }).click();

  // Ya no debe aparecer en la bandeja (volvió a comite_evaluacion, no es
  // ninguno de los 4 resultados que lista Gestión de Créditos).
  await expect(page).toHaveURL(/\/gestion-creditos$/, { timeout: 10000 });
  await expect(page.locator('tr', { hasText: 'GC191-PW-SIN-DOCS' })).toHaveCount(0);
});

test('pendiente_comite con documentos: cliente reenvía, coordinador aprueba, vuelve a comite_evaluacion (SCRUM-236)', async ({ page }) => {
  // --- 1. Coordinador notifica requiriendo documentos ---
  await loginAs(page, '1234', '1234', 'coordinador_comercial');
  await page.goto('/gestion-creditos');
  const filaInicial = page.locator('tr', { hasText: 'GC191-PW-CON-DOCS' });
  await expect(filaInicial).toBeVisible({ timeout: 10000 });
  await filaInicial.getByRole('button', { name: /Gestionar/i }).click();
  await expect(page).toHaveURL(/\/gestion-creditos\/\d+$/);

  await page.locator('input[type="email"]').fill('cliente@test.com');
  await page.getByPlaceholder('Asunto del correo').fill('Solicitud aplazada — falta documentación');
  await page.getByPlaceholder('Mensaje que verá el cliente').fill('Por favor envíe la documentación solicitada.');
  await page.locator('.radio-option', { hasText: 'Sí' }).click(); // requiere_documentos = Sí
  await page.locator('.form-group', { hasText: 'Documentación Requerida' }).locator('select').selectOption({ label: 'Preset Playwright 191' });

  await page.getByRole('button', { name: /Registrar y enviar notificación/i }).click();
  await page.getByRole('button', { name: 'Sí, enviar' }).click();
  await expect(page.getByText('¡Listo!')).toBeVisible({ timeout: 10000 });
  await page.getByRole('button', { name: 'OK' }).click();

  // --- 2. Cliente reenvía el documento solicitado ---
  await loginAs(page, '2345', '2345', 'cliente');
  await page.goto('/client-upload');
  await expect(page.getByText('Certificado bancario Playwright 191')).toBeVisible({ timeout: 10000 });

  await page.locator('.item-row', { hasText: 'Certificado bancario Playwright 191' })
    .locator('input[type="file"]')
    .setInputFiles({
      name: 'certificado-bancario.pdf',
      mimeType: 'application/pdf',
      buffer: Buffer.from('%PDF-1.4 contenido de prueba Playwright SCRUM-191'),
    });
  await expect(page.locator('.item-row', { hasText: 'Certificado bancario Playwright 191' }).getByText('SUBIDO')).toBeVisible({ timeout: 15000 });

  // --- 3. Coordinador aprueba el documento reenviado ---
  await loginAs(page, '1234', '1234', 'coordinador_comercial');
  await page.goto('/gestion-creditos');
  const filaGestionada = page.locator('tr', { hasText: 'GC191-PW-CON-DOCS' });
  await expect(filaGestionada).toBeVisible({ timeout: 10000 });
  await filaGestionada.getByRole('button', { name: /Ver/i }).click();
  await expect(page).toHaveURL(/\/gestion-creditos\/\d+$/);

  await expect(page.getByText('Documentos reenviados por el cliente')).toBeVisible();
  await page.getByRole('button', { name: 'Aprobar' }).first().click();
  await page.getByRole('button', { name: 'Sí, aprobar' }).click();

  // El mismo texto aparece tanto en el Swal como en el banner de la
  // página (una vez se actualiza el estado local) — se espera el Swal
  // específicamente para no chocar con el "strict mode" de Playwright.
  await expect(page.getByLabel('¡Listo!').getByText('el crédito vuelve a estar disponible para una nueva Acta de Comité')).toBeVisible({ timeout: 10000 });
  await page.getByRole('button', { name: 'OK' }).click();

  // --- 4. SCRUM-236: el crédito vuelve a comite_evaluacion, no a
  // aprobada_garantias — sale por completo de la bandeja de Gestión de
  // Créditos (comite_evaluacion no es ninguno de sus resultados) y queda
  // disponible de nuevo para incluirse en una nueva Acta de Comité.
  await page.goto('/gestion-creditos');
  await expect(page.locator('tr', { hasText: 'GC191-PW-CON-DOCS' })).toHaveCount(0);
});

test('el cliente no puede ver el Acta de Comité desde su crédito', async ({ page }) => {
  await loginAs(page, '2345', '2345', 'cliente');

  await page.goto('/creditos');
  await page.locator('.credit-item-card', { hasText: 'GC191-PW-ACTA' }).click();
  await expect(page.getByText('Acta de Comité Firmada')).toBeVisible({ timeout: 10000 });

  // Ni el link "Ver" del Acta Firmada, ni el panel legacy dirigido al
  // Comité deben aparecer para el rol cliente.
  await expect(page.locator('.doc-box-new', { hasText: 'Acta de Comité Firmada' }).getByRole('link', { name: 'Ver' })).toHaveCount(0);
  await expect(page.getByText('Como Comité de Crédito')).toHaveCount(0);
});
