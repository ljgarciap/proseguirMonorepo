import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * Validación visual SCRUM-193 (Registro de Crédito en CYF) + SCRUM-205
 * (Formalización de Garantías), 2026-08-17. Requiere haber corrido antes
 * (el fixture resetea el crédito GC193205-PW-1 a 'aprobada_garantias' en
 * cada corrida, así que se puede re-ejecutar sin limpiar nada a mano):
 *
 *   docker cp e2e/fixtures/seed_scrum_193_205.php factoring_backend:/tmp/seed_scrum_193_205.php
 *   docker exec factoring_backend php artisan tinker --execute="require '/tmp/seed_scrum_193_205.php';"
 *
 * Recorrido completo: Coordinador notifica con preset de garantías →
 * cliente diligencia la garantía → el crédito avanza solo a "Pendiente
 * Formalización de Garantías" → Coordinador aprueba en SCRUM-205 → pasa a
 * "Pendiente Registro de Crédito en CYF" → Coordinador registra fecha +
 * radicado en SCRUM-193 → el crédito queda disponible para la aprobación
 * de Gerencia (flujo legacy, sin tocar).
 */

test('flujo completo: notificar garantías → cliente diligencia → Formalización de Garantías → Registro CYF', async ({ page }) => {
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

  // El crédito se queda en "Aprobada para garantías" (no formalizacion_garantias
  // legacy) mientras el cliente diligencia — sigue visible, gestionada=Sí.
  await expect(page.locator('tr', { hasText: 'GC193205-PW-1' })).toBeVisible();

  // --- 2. Cliente diligencia la garantía del preset ---
  await loginAs(page, '2345', '2345', 'cliente');
  await page.goto('/client-upload');
  await expect(page.getByText('Pagaré Playwright 193-205')).toBeVisible({ timeout: 10000 });

  await page.locator('.item-row', { hasText: 'Pagaré Playwright 193-205' })
    .locator('input[type="file"]')
    .setInputFiles({
      name: 'pagare-193-205.pdf',
      mimeType: 'application/pdf',
      buffer: Buffer.from('%PDF-1.4 contenido de prueba Playwright SCRUM-193-205'),
    });
  await expect(page.locator('.item-row', { hasText: 'Pagaré Playwright 193-205' }).getByText('SUBIDO')).toBeVisible({ timeout: 15000 });

  // --- 3. El crédito avanza solo a "Pendiente Formalización de Garantías" ---
  await loginAs(page, '1234', '1234', 'coordinador_comercial');
  await page.goto('/gestion-creditos');
  const filaFormalizacion = page.locator('tr', { hasText: 'GC193205-PW-1' });
  await expect(filaFormalizacion).toBeVisible({ timeout: 10000 });
  await expect(filaFormalizacion.locator('.status-pill')).toHaveText(/Pendiente Formalización de Garantías/i);

  await filaFormalizacion.getByRole('button', { name: /Gestionar/i }).click();
  await expect(page).toHaveURL(/\/gestion-creditos\/\d+\/formalizacion-garantias$/);

  await expect(page.getByText('Pagaré Playwright 193-205')).toBeVisible({ timeout: 10000 });
  await page.locator('tr', { hasText: 'Pagaré Playwright 193-205' }).locator('select').selectOption('aprobada');

  await page.getByRole('button', { name: /Guardar validación/i }).click();
  await page.getByRole('button', { name: 'Sí, guardar' }).click();
  await expect(page.getByText('¡Listo!')).toBeVisible({ timeout: 10000 });
  await page.getByRole('button', { name: 'OK' }).click();
  await expect(page).toHaveURL(/\/gestion-creditos$/, { timeout: 10000 });

  // --- 4. Pasa a "Pendiente Registro de Crédito en CYF" ---
  const filaCyf = page.locator('tr', { hasText: 'GC193205-PW-1' });
  await expect(filaCyf).toBeVisible({ timeout: 10000 });
  await expect(filaCyf.locator('.status-pill')).toHaveText(/Pendiente Registro de Crédito en CYF/i);

  await filaCyf.getByRole('button', { name: /Gestionar/i }).click();
  await expect(page).toHaveURL(/\/gestion-creditos\/\d+\/registro-cyf$/);

  await expect(page.getByText('Aprobada')).toBeVisible();
  await page.locator('input[type="date"]').fill('2026-08-17');
  await page.getByPlaceholder('Ingrese el número de radicado').fill('RAD-PW-193205');

  await page.getByRole('button', { name: 'save Guardar' }).click();
  await page.getByRole('button', { name: 'Sí, guardar' }).click();
  await expect(page.getByText('¡Listo!')).toBeVisible({ timeout: 10000 });
  await page.getByRole('button', { name: 'OK' }).click();
  await expect(page).toHaveURL(/\/gestion-creditos$/, { timeout: 10000 });

  // El crédito sale de las 6 tarjetas de Gestión de Créditos (quedó en
  // 'aprobacion_registro_cyf', legacy, fuera de queryBase()) — el flujo
  // continúa en la pantalla de Crédito Ordinario para Gerencia, sin tocar.
  await expect(page.locator('tr', { hasText: 'GC193205-PW-1' })).toHaveCount(0);
});
