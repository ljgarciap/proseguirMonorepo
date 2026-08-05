import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-178 — Gestión de Créditos. Valida los 4 flujos contra datos
 * sembrados en el entorno local con el prefijo GC-PW- (ver
 * scratchpad/seed_gestion_creditos.php de la sesión que implementó el
 * ticket). Requiere ng serve (localhost:4200) + backend Docker
 * (localhost:8000) corriendo.
 */
test.describe('Gestión de Créditos (SCRUM-178)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, '1234', '1234', 'coordinador_comercial');
    await page.goto('/gestion-creditos');
    await expect(page.locator('h1')).toHaveText('Gestión de Créditos');
  });

  test('la bandeja muestra tarjetas y las 4 solicitudes sembradas', async ({ page }) => {
    await expect(page.locator('.tile-value').first()).toBeVisible();

    for (const numero of ['GC-PW-APR', 'GC-PW-SAR', 'GC-PW-REC', 'GC-PW-PEN']) {
      await expect(page.locator('.modern-table')).toContainText(numero);
    }
  });

  test('Aprobada para garantías: gestionar transiciona y notifica', async ({ page }) => {
    const fila = page.locator('tr', { hasText: 'GC-PW-APR' });
    await fila.getByRole('button', { name: /Gestionar/i }).click();

    await expect(page.locator('h1')).toContainText('Gestión de Garantías');
    await expect(page.locator('.content-card', { hasText: 'Información del Comité' })).toBeVisible();

    await page.locator('input[type="email"]').fill('gc.playwright@test.com');
    await page.getByPlaceholder('Asunto del correo').fill('Aprobación de garantías — prueba Playwright');
    await page.getByPlaceholder('Mensaje que verá el cliente').fill('Debe formalizar las garantías (mensaje de prueba).');

    const presetSelect = page.locator('select.pro-input');
    await presetSelect.selectOption({ index: 1 });

    await page.getByRole('button', { name: /Registrar y enviar notificación/i }).click();
    await page.getByRole('button', { name: 'Sí, enviar' }).click();

    await expect(page.getByText('La gestión fue registrada')).toBeVisible({ timeout: 10000 });
    await page.getByRole('button', { name: 'OK' }).click();

    await expect(page).toHaveURL(/gestion-creditos$/);
  });

  test('SARLAFT desfavorable: muestra información de validación y PDF', async ({ page }) => {
    const fila = page.locator('tr', { hasText: 'GC-PW-SAR' });
    await fila.getByRole('button', { name: /Gestionar/i }).click();

    await expect(page.locator('.content-card', { hasText: 'Información de la Validación' })).toBeVisible();
    await expect(page.locator('.content-card', { hasText: 'Información de la Validación' })).toContainText('Desfavorable');
    await expect(page.getByText('Ver / descargar PDF')).toBeVisible();

    await page.getByPlaceholder('Asunto del correo').fill('Resultado de su solicitud — prueba Playwright');
    await page.getByPlaceholder('Mensaje que verá el cliente').fill('Su solicitud no continúa en el proceso (mensaje de prueba).');

    await page.getByRole('button', { name: /Registrar y enviar notificación/i }).click();
    await page.getByRole('button', { name: 'Sí, enviar' }).click();

    await expect(page.getByText('La gestión fue registrada')).toBeVisible({ timeout: 10000 });
  });

  test('Pendiente por Comité: exige responder si requiere documentación', async ({ page }) => {
    const fila = page.locator('tr', { hasText: 'GC-PW-PEN' });
    await fila.getByRole('button', { name: /Gestionar/i }).click();

    await expect(page.locator('.content-card', { hasText: 'Información del Comité' })).toBeVisible();

    await page.getByPlaceholder('Asunto del correo').fill('Solicitud aplazada — prueba Playwright');
    await page.getByPlaceholder('Mensaje que verá el cliente').fill('El Comité aplazó su decisión (mensaje de prueba).');

    // Sin responder Sí/No -> el submit debe bloquear con alerta de validación.
    await page.getByRole('button', { name: /Registrar y enviar notificación/i }).click();
    await expect(page.getByText('Indique si el cliente debe enviar documentación')).toBeVisible();
    await page.getByRole('button', { name: 'OK' }).click();

    // Responder "Sí" habilita/exige el preset.
    await page.locator('label.radio-option', { hasText: 'Sí' }).click();
    await page.getByRole('button', { name: /Registrar y enviar notificación/i }).click();
    await expect(page.getByText('Seleccione la documentación requerida')).toBeVisible();
    await page.getByRole('button', { name: 'OK' }).click();

    await page.locator('select.pro-input').selectOption({ index: 1 });
    await page.getByRole('button', { name: /Registrar y enviar notificación/i }).click();
    await page.getByRole('button', { name: 'Sí, enviar' }).click();

    await expect(page.getByText('La gestión fue registrada')).toBeVisible({ timeout: 10000 });
  });
});

test.describe('Gestión de Créditos — caminos de ruptura', () => {
  test('rol cliente no puede acceder a la bandeja', async ({ page }) => {
    await loginAs(page, '1234', '1234', 'cliente');
    await page.goto('/gestion-creditos');
    await expect(page).not.toHaveURL(/gestion-creditos$/, { timeout: 10000 });
  });

  test('solicitud ya gestionada se abre en modo solo lectura, sin botón de envío', async ({ page }) => {
    await loginAs(page, '1234', '1234', 'coordinador_comercial');
    await page.goto('/gestion-creditos');
    // GC-PW-SAR ya fue gestionada por el test anterior y, a diferencia de
    // GC-PW-APR (que al gestionarse pasa a formalizacion_garantias y sale
    // de esta bandeja), se queda en estado 'rechazado' — sigue visible acá.
    const fila = page.locator('tr', { hasText: 'GC-PW-SAR' });
    await fila.getByRole('button', { name: /Ver/i }).click();

    await expect(page.getByText('Estás viendo esta solicitud en modo solo lectura')).toBeVisible();
    await expect(page.getByRole('button', { name: /Registrar y enviar notificación/i })).toHaveCount(0);
    await expect(page.locator('input[type="email"]')).toBeDisabled();
  });
});
