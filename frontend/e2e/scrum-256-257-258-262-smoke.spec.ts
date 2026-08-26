import { test, expect } from '@playwright/test';
import { execFileSync } from 'child_process';
import { loginAs } from './helpers/auth';

function tinker(php: string): string {
  return execFileSync('docker', ['exec', 'factoring_backend', 'php', 'artisan', 'tinker', '--execute', php]).toString();
}

/**
 * Smoke visual del batch SCRUM-256/257/258/262 (2026-08-26). Cobertura
 * funcional ya está en PHPUnit (CreditoOrdinarioTest, ValidacionDocumental
 * NotificationTest, InformeTecnicoTest) — esto valida específicamente lo
 * que solo se ve en el navegador real: que el expediente de Etapa 1 no
 * duplica el documento tras cargarlo (SCRUM-256) y que el wording
 * Rechazado→Negado quedó consistente en los 2 puntos de UI que lo piden
 * (SCRUM-257).
 */
test('Etapa 1: un solo archivo tras subir, botón Subir desaparece, y wording Negar Solicitud', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'superadmin');

  // Crea un crédito Ordinario nuevo directo vía tinker (mismo mecanismo de
  // fixture que el resto de la suite) para no depender del flujo completo
  // de Solicitud de Crédito solo para este smoke.
  const clienteId = tinker(`echo \\App\\Models\\User::where('numero_documento','2345')->first()->id;`).trim();
  const creditoId = tinker(`echo \\App\\Models\\CreditoOrdinario::iniciar(clienteId: ${clienteId}, monto: 15000000, plazoMeses: 12, usuario: 'PW', rol: 'superadmin', comentario: 'Fixture smoke 256/257')->id;`).trim();

  await page.goto(`/creditos/${creditoId}`);
  await expect(page.getByRole('heading', { name: /Registro e Identificación/i }).or(page.getByText('Etapa 1: Registro e Identificación'))).toBeVisible({ timeout: 10000 });

  const docBox = page.locator('.doc-box-new', { hasText: 'Formulario de Solicitud' });
  await expect(docBox.getByText('Pendiente')).toBeVisible();
  await expect(docBox.getByText('Subir')).toBeVisible();

  // Sube el único documento legacy 'formulario_solicitud'. onMultiFileUpload
  // abre un modal de confirmación (SweetAlert) antes de postear.
  await docBox.locator('input[type="file"]').setInputFiles({
    name: 'formulario.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from('%PDF-1.4 contenido de prueba'),
  });
  await page.getByRole('button', { name: 'Confirmar y Avanzar' }).click();
  await page.getByRole('heading', { name: '¡Procesado!' }).waitFor({ state: 'visible', timeout: 10000 });
  await page.getByRole('button', { name: 'OK' }).click();

  // SCRUM-256: exactamente 1 archivo listado (antes salían 2 — el mismo
  // upload contado por documentos_raw y por DocumentRequestItem.upload).
  await expect(docBox.getByText('Cargado (1)')).toBeVisible({ timeout: 10000 });
  await expect(docBox.locator('.btn-view-doc')).toHaveCount(1);

  // SCRUM-256: el botón "Subir" ya no debe ofrecerse para este documento.
  await expect(docBox.getByText('Subir')).toHaveCount(0);

  // SCRUM-257: el botón de Etapa 1 dice "Negar Solicitud", no "Rechazar Solicitud".
  await expect(page.getByRole('button', { name: 'Negar Solicitud' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Rechazar Solicitud' })).toHaveCount(0);
});

test('Gestión de Créditos: tarjeta y filtro dicen Negados, no Rechazados', async ({ page }) => {
  await loginAs(page, '7890', '7890', 'coordinador_comercial');
  await page.goto('/gestion-creditos');

  await expect(page.getByText('Negados - Comité de Créditos')).toBeVisible({ timeout: 10000 });
  await expect(page.getByText('Rechazados - Comité de Créditos')).toHaveCount(0);
  await expect(page.locator('option', { hasText: 'Negada por Comité' })).toHaveCount(1);
});
