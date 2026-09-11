import { test, expect } from '@playwright/test';
import { execFileSync } from 'child_process';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-345 (rebote, aclarado por Luis 2026-09-11): "Anexos" — archivos
 * sueltos sin preset ni clave predefinida (foto, Word, PDF externo,
 * cualquier nombre), que el Director de Crédito puede cargar en cualquier
 * etapa del proceso, asociados al crédito y visibles para el resto de
 * roles internos — nunca para el cliente.
 *
 * Requiere: ng serve (localhost:4200) + backend Docker corriendo.
 */

function tinker(php: string): string {
  return execFileSync('docker', ['exec', 'factoring_backend', 'php', 'artisan', 'tinker', '--execute', php]).toString();
}

test('Director de Crédito carga un anexo en cualquier etapa; visible internamente, oculto para el cliente', async ({ page }) => {
  // clienteId = el propio Super Administrador (doc 1234, tiene también el
  // rol 'cliente'): así el paso 3 (probar que el cliente NO ve Anexos)
  // puede reusar la misma cuenta con el role-switcher — CreditoOrdinarioController::autorizarPropiedad()
  // solo deja ver el crédito al 'cliente' dueño, así que probarlo con un
  // cliente ajeno (doc 2345) nunca habría llegado a ver la pantalla.
  const creditoId = tinker(`
    $c = \\App\\Models\\CreditoOrdinario::iniciar(
      clienteId: \\App\\Models\\User::where('numero_documento', '1234')->first()->id,
      monto: 5000000,
      plazoMeses: 12,
      usuario: 'Playwright',
      rol: 'coordinador_comercial',
      comentario: 'Fixture SCRUM-345 anexos.'
    );
    $c->update(['estado' => 'desembolso_ingreso']);
    echo $c->id;
  `).trim().split('\n').pop();

  // --- 1. Director de Crédito carga un anexo genérico (Word, no un PDF del preset) ---
  await loginAs(page, '1234', '1234', 'coordinador_comercial');
  await page.goto(`/creditos/${creditoId}`);

  const anexosCard = page.locator('.documents-card', { hasText: 'Anexos' });
  await expect(anexosCard).toBeVisible({ timeout: 10000 });

  // Laravel valida el tipo real del archivo (magic bytes), no el
  // Content-Type declarado — un buffer de texto con extensión .docx sin
  // firma real de docx es rechazado por la regla 'mimes:' aunque el
  // backend sí acepte docx/imágenes reales (ver tests unitarios de
  // CreditoOrdinarioAnexoTest). Mismo header %PDF- que el resto de la
  // suite e2e usa para simular un PDF válido.
  await anexosCard.locator('input[type="file"]').setInputFiles({
    name: 'valoracion-externa.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from('%PDF-1.4 contenido de prueba Playwright SCRUM-345 — anexo sin preset'),
  });

  await expect(anexosCard.getByText('valoracion-externa.pdf')).toBeVisible({ timeout: 10000 });

  // --- 2. Otro rol interno (Operativo, no es quien lo cargó) lo ve y puede descargarlo ---
  const switcher = page.locator('.test-role-switcher select');
  await switcher.selectOption('operativo');
  await page.goto(`/creditos/${creditoId}`);
  const anexosCardOperativo = page.locator('.documents-card', { hasText: 'Anexos' });
  await expect(anexosCardOperativo.getByText('valoracion-externa.pdf')).toBeVisible({ timeout: 10000 });
  await expect(anexosCardOperativo.getByRole('link', { name: /Ver/i })).toBeVisible();
  // Operativo no es Director de Crédito: no ve el control de carga.
  await expect(anexosCardOperativo.locator('input[type="file"]')).toHaveCount(0);

  // --- 3. El cliente no ve la sección de Anexos en absoluto (uso interno) ---
  await switcher.selectOption('cliente');
  await page.goto(`/creditos/${creditoId}`);
  await expect(page.locator('.documents-card', { hasText: 'Expediente de Documentos' })).toBeVisible({ timeout: 10000 });
  await expect(page.locator('.documents-card', { hasText: 'Anexos' })).toHaveCount(0);
});
