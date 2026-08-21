import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-242 — la lista "Clientes con Cartera" (Dashboard, pestaña Cartera
 * Factoring) debe mostrarse de mayor a menor valor de cartera; antes
 * ordenaba ascendente. Requiere el seed de
 * e2e/fixtures/seed_scrum_230_cartera_factoring.php.
 */
test('Clientes con Cartera se muestra de mayor a menor cartera', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'operativo');
  await page.goto('/dashboard');
  await page.getByRole('button', { name: /Cartera Factoring/i }).click();

  await expect(page.getByText('Orden descendente por valor de cartera y luego por nombre')).toBeVisible({ timeout: 10000 });

  const filas = page.locator('.table-container', { hasText: 'Clientes con Cartera' }).locator('tbody tr');
  const nombres = await filas.allTextContents();
  const idxUno = nombres.findIndex(t => t.includes('Cliente Playwright CF Dos') === false && t.includes('Cliente Playwright CF'));
  const idxDos = nombres.findIndex(t => t.includes('Cliente Playwright CF Dos'));
  expect(idxUno).toBeGreaterThanOrEqual(0);
  expect(idxDos).toBeGreaterThanOrEqual(0);
  // Cliente Playwright CF (3.000.000) debe ir antes que Cliente Playwright CF Dos (2.000.000)
  expect(idxUno).toBeLessThan(idxDos);
});
