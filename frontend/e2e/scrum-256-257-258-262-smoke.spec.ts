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

/**
 * SCRUM-257 (comentario Juan Andrés, 2026-08-26): la tarjeta lateral de
 * Crédito Ordinario ya decía "Negado" — el badge "ESTADO ACTUAL" y la línea
 * "Estado actual de la etapa" de la cabecera del detalle quedaron sin
 * cubrir, seguían mostrando "Rechazado".
 */
test('Crédito Ordinario: badge y línea de estado de la cabecera dicen Negado', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'superadmin');

  const clienteId = tinker(`echo \\App\\Models\\User::where('numero_documento','2345')->first()->id;`).trim();
  const creditoId = tinker(`
    $c = \\App\\Models\\CreditoOrdinario::iniciar(clienteId: ${clienteId}, monto: 12000000, plazoMeses: 12, usuario: 'PW', rol: 'superadmin', comentario: 'Fixture smoke 257 badge');
    $c->estado = 'rechazado';
    $c->save();
    echo $c->id;
  `).trim();

  await page.goto(`/creditos/${creditoId}`);
  await expect(page.locator('.status-detail-badge')).toHaveText('Negado', { timeout: 10000 });
  await expect(page.getByText('Estado actual de la etapa:')).toContainText('Negado');
  await expect(page.getByText('Estado actual de la etapa:')).not.toContainText('Rechazado');
});

/**
 * SCRUM-256 (comentario Juan Andrés, 2026-08-26): el cliente pudo cargar el
 * mismo archivo físico como 2 documentos distintos del expediente (cada uno
 * quedaba "Cargado (1)" válido por separado). El backend ahora lo rechaza
 * comparando por hash de contenido (DuplicateDocumentGuard) — cobertura de
 * integración ya está en PHPUnit; esto confirma que el error real llega al
 * SweetAlert que ve el usuario, no solo que el endpoint devuelve 422.
 */
test('Etapa 1 (preset): no permite el mismo archivo para 2 documentos distintos', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'superadmin');

  const fixture = tinker(`
    $cliente = \\App\\Models\\Cliente::where('numero_documento','2345')->first();
    $user = \\App\\Models\\User::where('numero_documento','2345')->first();
    $admin = \\App\\Models\\User::where('numero_documento','1234')->first();
    $tipoCredito = \\App\\Models\\TipoCredito::where('codigo','ORDINARIO')->first();
    $amort = \\App\\Models\\Amortizacion::where('codigo','MENSUAL')->first();
    $preset = \\App\\Models\\DocumentPreset::create(['nombre' => 'Preset PW Dup ' . now()->timestamp, 'descripcion' => 'Fixture smoke 256 dup']);
    $req1 = \\App\\Models\\DocumentRequirement::create(['nombre' => 'RUT Playwright Dup', 'activo' => true]);
    $req2 = \\App\\Models\\DocumentRequirement::create(['nombre' => 'Cedula Playwright Dup', 'activo' => true]);
    $preset->requirements()->attach([$req1->id, $req2->id]);
    $solicitud = \\App\\Models\\SolicitudCredito::create(['cliente_id' => $cliente->id, 'usuario_registra_id' => $admin->id, 'tipo_credito_id' => $tipoCredito->id, 'monto_solicitado' => 10000000, 'plazo_meses' => 12, 'amortizacion_id' => $amort->id, 'destino_recurso' => 'Capital', 'fuente_pago' => 'Ventas', 'correo_notificacion' => 'cliente@test.com', 'asunto_notificacion' => 'Asunto', 'mensaje_notificacion' => 'Mensaje']);
    $dr = \\App\\Models\\DocumentRequest::create(['cliente_id' => $user->id, 'creado_por' => $admin->id, 'estado' => 'pendiente', 'etapa' => 'inicial', 'preset_id' => $preset->id, 'preset_nombre' => $preset->nombre, 'solicitud_credito_id' => $solicitud->id]);
    $item1 = \\App\\Models\\DocumentRequestItem::create(['document_request_id' => $dr->id, 'document_requirement_id' => $req1->id, 'estado' => 'pendiente']);
    $item2 = \\App\\Models\\DocumentRequestItem::create(['document_request_id' => $dr->id, 'document_requirement_id' => $req2->id, 'estado' => 'pendiente']);
    $credito = \\App\\Models\\CreditoOrdinario::iniciar(clienteId: $user->id, monto: 10000000, plazoMeses: 12, usuario: $admin->name, rol: 'superadmin', comentario: 'Fixture smoke 256 dup', solicitudCreditoId: $solicitud->id);
    echo $credito->id;
  `).trim();
  const creditoId = fixture;

  await page.goto(`/creditos/${creditoId}`);
  await expect(page.getByText('Etapa 1: Registro e Identificación')).toBeVisible({ timeout: 10000 });

  const mismoContenido = Buffer.from('%PDF-1.4 mismo contenido fisico SCRUM-256');

  const rutBox = page.locator('.doc-box-new', { hasText: 'RUT Playwright Dup' });
  await rutBox.locator('input[type="file"]').setInputFiles({ name: 'rut.pdf', mimeType: 'application/pdf', buffer: mismoContenido });
  await page.getByRole('button', { name: 'Confirmar y Avanzar' }).click();
  await page.getByRole('heading', { name: '¡Procesado!' }).waitFor({ state: 'visible', timeout: 10000 });
  await page.getByRole('button', { name: 'OK' }).click();
  await expect(rutBox.getByText('Cargado (1)')).toBeVisible({ timeout: 10000 });

  const cedulaBox = page.locator('.doc-box-new', { hasText: 'Cedula Playwright Dup' });
  await cedulaBox.locator('input[type="file"]').setInputFiles({ name: 'cedula.pdf', mimeType: 'application/pdf', buffer: mismoContenido });
  await page.getByRole('button', { name: 'Confirmar y Avanzar' }).click();

  await expect(page.getByRole('heading', { name: 'Error' })).toBeVisible({ timeout: 10000 });
  await expect(page.getByText('Este archivo ya fue cargado como "RUT Playwright Dup"')).toBeVisible();
  await page.getByRole('button', { name: 'OK' }).click();

  // El 2° documento sigue pendiente — no quedó "Cargado" con el archivo duplicado.
  await expect(cedulaBox.getByText('Pendiente')).toBeVisible();
});
