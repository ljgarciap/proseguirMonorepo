import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-230 — pestaña "Cartera Factoring" del Dashboard (frontend). El
 * backend (endpoint GET /dashboard/cartera-factoring + export Excel) se
 * implementó y validó en la sesión anterior (2026-08-19). Este spec valida
 * la pestaña nueva: indicadores, Top 10 clientes, panel de clientes con
 * búsqueda, detalle paginado y export a Excel (solo Superadmin).
 *
 * Requiere haber corrido antes (idempotente, se puede re-ejecutar sin
 * limpiar nada a mano):
 *
 *   docker cp e2e/fixtures/seed_scrum_230_cartera_factoring.php factoring_backend:/tmp/seed_scrum_230_cartera_factoring.php
 *   docker exec factoring_backend php artisan tinker --execute="require '/tmp/seed_scrum_230_cartera_factoring.php';"
 *
 * Validado también con datos 100% reales de la sesión 2026-08-19 (PDF real
 * "Compraventa_opf sumatec 2.pdf" del ticket, subido vía OCR real —
 * resultado exacto: 24 registros de origen, 15 Factoring + 9 CompraVenta,
 * 0 coincidencias, $0 pagado — igual al "control con archivos adjuntos"
 * documentado en el prototipo adjunto al ticket). Este spec usa datos
 * sintéticos (PW-CF-) en vez de los reales porque necesita reproducir
 * también el camino "con pago" (Top10/clientes poblados), que los 4 PDFs
 * de referencia no ejercitan por sí solos.
 *
 * Requiere: ng serve (localhost:4200) + backend Docker corriendo.
 */

test('Operativo ve indicadores, Top10, clientes y detalle en la pestaña Cartera Factoring', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'operativo');
  await page.goto('/dashboard');

  await page.getByRole('button', { name: /Cartera Factoring/i }).click();

  // Indicadores
  await expect(page.getByText('Facturas de Origen')).toBeVisible({ timeout: 10000 });
  await expect(page.getByText('Valor Cartera Total Después de Pago')).toBeVisible();
  await expect(page.getByText('Pagos Coincidentes')).toBeVisible();
  await expect(page.getByText('Registros Sin Pago')).toBeVisible();

  // Top 10 clientes: "Cliente Playwright CF" tiene saldo 3.000.000 > 0
  await expect(page.getByText('Top 10 Clientes por Cartera')).toBeVisible();

  // Panel de clientes con búsqueda
  const buscador = page.getByPlaceholder('Buscar por documento o nombre...');
  await expect(buscador).toBeVisible();
  await buscador.fill('900999888');
  await expect(page.getByText('Cliente Playwright CF').first()).toBeVisible();

  // Detalle: la factura sin pago (PW-CF-002) debe verse sin datos de pago,
  // pero SCRUM-241: "Saldo Después del Pago" debe ser igual al Valor Neto
  // Factura ($2.000.000), no vacío/"-".
  await expect(page.getByText('Detalle de Cartera')).toBeVisible();
  const filaSinPago = page.locator('tr', { hasText: 'PW-CF-002' });
  await expect(filaSinPago).toBeVisible();
  // Valor Neto Factura y Saldo Después del Pago deben coincidir ($2.000.000
  // cada uno) — por eso hay 2 coincidencias del mismo texto en la fila.
  await expect(filaSinPago.getByText('$ 2.000.000')).toHaveCount(2);

  // La factura con pago (PW-CF-001) sí debe traer saldo después de pago
  // (8.000.000 - 5.000.000 = 3.000.000, SCRUM-241)
  const filaConPago = page.locator('tr', { hasText: 'PW-CF-001' });
  await expect(filaConPago).toBeVisible();
  await expect(filaConPago.getByText('Pagador Real PW')).toBeVisible();
  await expect(filaConPago.getByText('$ 3.000.000')).toBeVisible();

  // SCRUM-241: al corregir la fórmula, el saldo de PW-CF-002 (sin pago) deja
  // de ser NULL y ahora suma en las gráficas inferiores (antes quedaban
  // vacías: "Sin saldos de cartera para graficar").
  await buscador.fill('900777666');
  await expect(page.getByText('Cliente Playwright CF Dos').first()).toBeVisible();

  // Filtro por Factura N.º (solo visible en esta pestaña)
  const filtroFactura = page.getByPlaceholder('Número...');
  await expect(filtroFactura).toBeVisible();
  await filtroFactura.fill('PW-CF-002');
  await filtroFactura.press('Enter');
  await expect(page.locator('tr', { hasText: 'PW-CF-001' })).toHaveCount(0, { timeout: 10000 });
  await expect(page.locator('tr', { hasText: 'PW-CF-002' })).toBeVisible();

  // Operativo NO debe ver el botón de exportar Excel (solo Superadmin)
  await expect(page.getByRole('button', { name: /Exportar Excel/i })).toHaveCount(0);
});

test('Superadmin puede exportar Cartera Factoring a Excel', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'superadmin');
  await page.goto('/dashboard');

  await page.getByRole('button', { name: /Cartera Factoring/i }).click();
  await expect(page.getByText('Facturas de Origen')).toBeVisible({ timeout: 10000 });

  const exportBtn = page.getByRole('button', { name: /Exportar Excel/i });
  await expect(exportBtn).toBeVisible();

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    exportBtn.click(),
  ]);
  expect(download.suggestedFilename()).toMatch(/cartera_factoring_.*\.xlsx/);
});

test('Gerente no ve la pestaña Cartera Factoring', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'gerente');
  await page.goto('/dashboard');

  await expect(page.getByRole('button', { name: /Cartera CYF/i })).toBeVisible({ timeout: 10000 });
  await expect(page.getByRole('button', { name: /Cartera Factoring/i })).toHaveCount(0);
});
