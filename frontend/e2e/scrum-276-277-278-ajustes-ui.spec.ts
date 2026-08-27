import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-276/277/278 (2026-08-27) — 3 ajustes de UI reportados por Juan Andrés:
 *
 * 1. SCRUM-276 — Informe Técnico, sección Coordinador Comercial: el campo
 *    "Crédito Solicitado" se prellena con el monto ya solicitado en el
 *    crédito (credito.monto), sigue editable.
 * 2. SCRUM-277 — Acta de Comité: el <input type="time"> nativo (picker de
 *    rueda del SO, con espacio en blanco entre columnas de distinta altura)
 *    se reemplaza por <app-time-select-12h> (3 <select> propios, formato
 *    12h con a. m./p. m.), tanto en "Hora de inicio" (tab 1) como en
 *    "Termina la reunión a las..." (tab 5).
 * 3. SCRUM-278 — Acta de Comité: los campos de monto en las pestañas
 *    "Decisión" (Monto) y "Resumen" (Decisión) quedan alineados a la
 *    derecha.
 */

function tinker(php: string): string {
  const fs = require('fs');
  const tmp = '/tmp/_scrum276277278_e2e_tinker.php';
  fs.writeFileSync(tmp, php);
  return execSync(`docker exec -i factoring_backend php artisan tinker < ${tmp}`).toString();
}

let creditoInformeTecnicoId: string;

test.beforeAll(() => {
  // Limpia cualquier acta pendiente/borrador de una corrida previa
  // interrumpida (mismo mecanismo que SCRUM-198/243).
  const salida = tinker(`
    \\App\\Models\\ActaComite::whereIn('estado', ['pendiente', 'borrador'])->get()->each(function ($a) {
      \\App\\Models\\ActaComiteSolicitud::where('acta_comite_id', $a->id)->delete();
      $a->delete();
    });
    \\App\\Models\\CreditoOrdinario::where('numero_solicitud', 'SC278-MONTO-ALIGN')->delete();
    \\App\\Models\\CreditoOrdinario::where('numero_solicitud', 'SC276-CREDITO-SOLICITADO')->delete();

    $docCC = \\App\\Models\\DocumentType::firstOrCreate(['codigo' => 'CC'], ['nombre' => 'Cédula']);
    $tipoNatural = \\App\\Models\\TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
    $tipoOrdinario = \\App\\Models\\TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
    $tipoConstructor = \\App\\Models\\TipoCredito::firstOrCreate(['codigo' => 'CONSTRUCTOR'], ['nombre' => 'Crédito Constructor']);
    $amort = \\App\\Models\\Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);
    $admin = \\App\\Models\\User::where('numero_documento', '1234')->firstOrFail();

    // --- Credito para el Acta de Comité (SCRUM-277/278) ---
    $clienteActa = \\App\\Models\\Cliente::firstOrCreate(
      ['numero_documento' => '900777900'],
      ['tipo_persona_id' => $tipoNatural->id, 'tipo_documento_id' => $docCC->id,
       'identificacion' => '900777900', 'nombre' => 'Cliente Playwright SC278',
       'nombres' => 'Cliente', 'primer_apellido' => 'Playwright SC278',
       'correo_electronico' => 'sc278@test.com', 'telefono' => '3000000011',
       'direccion' => 'Calle SC278 1', 'pais' => 'Colombia', 'departamento' => 'Valle',
       'ciudad' => 'Cali', 'activo' => true]
    );
    $solicitudActa = \\App\\Models\\SolicitudCredito::create([
      'cliente_id' => $clienteActa->id, 'usuario_registra_id' => $admin->id,
      'tipo_credito_id' => $tipoOrdinario->id, 'monto_solicitado' => 8000000, 'plazo_meses' => 12,
      'amortizacion_id' => $amort->id, 'destino_recurso' => 'Capital de trabajo',
      'garantia' => 'Pagaré', 'fuente_pago' => 'Ventas del negocio',
      'correo_notificacion' => 'sc278@test.com', 'asunto_notificacion' => 'Doc',
      'mensaje_notificacion' => 'Adjunta.',
    ]);
    \\App\\Models\\CreditoOrdinario::create([
      'numero_solicitud' => 'SC278-MONTO-ALIGN', 'cliente_id' => $admin->id,
      'solicitud_credito_id' => $solicitudActa->id, 'monto' => 8000000, 'plazo_meses' => 12,
      'estado' => 'comite_evaluacion', 'documentos' => [],
    ]);

    // --- Credito Constructor para Informe Técnico (SCRUM-276), ya en la
    // sección que diligencia el Coordinador Comercial, sin informe previo
    // (InformeTecnicoController::show() lo crea en blanco al primer GET). ---
    $clienteIT = \\App\\Models\\Cliente::firstOrCreate(
      ['numero_documento' => '900777901'],
      ['tipo_persona_id' => $tipoNatural->id, 'tipo_documento_id' => $docCC->id,
       'identificacion' => '900777901', 'nombre' => 'Cliente Playwright SC276',
       'nombres' => 'Cliente', 'primer_apellido' => 'Playwright SC276',
       'correo_electronico' => 'sc276@test.com', 'telefono' => '3000000012',
       'direccion' => 'Calle SC276 1', 'pais' => 'Colombia', 'departamento' => 'Valle',
       'ciudad' => 'Cali', 'activo' => true]
    );
    $solicitudIT = \\App\\Models\\SolicitudCredito::create([
      'cliente_id' => $clienteIT->id, 'usuario_registra_id' => $admin->id,
      'tipo_credito_id' => $tipoConstructor->id, 'monto_solicitado' => 12345678, 'plazo_meses' => 24,
      'amortizacion_id' => $amort->id, 'destino_recurso' => 'Construcción',
      'garantia' => 'Hipoteca', 'fuente_pago' => 'Ventas del proyecto',
      'correo_notificacion' => 'sc276@test.com', 'asunto_notificacion' => 'Doc',
      'mensaje_notificacion' => 'Adjunta.',
    ]);
    $creditoIT = \\App\\Models\\CreditoOrdinario::create([
      'numero_solicitud' => 'SC276-CREDITO-SOLICITADO', 'cliente_id' => $admin->id,
      'solicitud_credito_id' => $solicitudIT->id, 'monto' => 12345678, 'plazo_meses' => 24,
      'estado' => 'informe_tecnico_coordinador', 'documentos' => [],
    ]);

    echo 'CREDITO_IT_ID=' . $creditoIT->id;
  `);

  const match = salida.match(/CREDITO_IT_ID=(\d+)/);
  if (!match) throw new Error('No se pudo sembrar el crédito de Informe Técnico: ' + salida);
  creditoInformeTecnicoId = match[1];
});

test('SCRUM-276: Crédito Solicitado se prellena con el monto del crédito, editable', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'coordinador_comercial');

  await page.goto(`/informes-tecnicos/${creditoInformeTecnicoId}`);
  const filaCreditoSolicitado = page.locator('tr', { hasText: 'Crédito Solicitado' });
  const inputCredito = filaCreditoSolicitado.locator('input');

  // Prellenado automático con credito.monto (12.345.678), sin que el
  // usuario haya tocado nada todavía.
  await expect(inputCredito).toHaveValue('12.345.678');
  await expect(inputCredito).toBeEnabled();

  // Sigue siendo editable — el prellenado es solo un valor por defecto.
  await inputCredito.fill('20.000.000');
  await inputCredito.blur();
  await expect(inputCredito).toHaveValue('20.000.000');
});

test('SCRUM-277: Hora de inicio / finalización usan selects propios (sin picker nativo)', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'coordinador_comercial');

  await page.goto('/actas-comite');
  await page.getByRole('button', { name: /Generar acta pendiente/i }).click();
  await page.getByRole('button', { name: 'OK' }).click();
  await expect.poll(() => page.url(), { timeout: 10000 }).toMatch(/\/actas-comite\/\d+$/);

  // --- Tab 1: Hora de inicio ---
  await page.getByRole('button', { name: '1. Orden del día' }).click();
  const campoInicio = page.locator('.form-field', { hasText: 'Hora de inicio' });
  await expect(campoInicio.locator('input[type="time"]')).toHaveCount(0);
  const selectsInicio = campoInicio.locator('app-time-select-12h select');
  await expect(selectsInicio).toHaveCount(3);

  // TimeSelect12hComponent.emit() no propaga nada hasta tener los 3 <select>
  // llenos (ver docstring del componente) — recién el 3ro dispara el único
  // guardado inmediato (onFieldChangeInmediato). waitForResponse espera la
  // respuesta real del PUT; waitForLoadState('networkidle') no sirve acá
  // porque puede resolver ANTES de que Angular llegue a emitir la petición
  // (zona/microtask de por medio), y el reload() de abajo cancelaría un PUT
  // que todavía no había salido.
  await selectsInicio.nth(0).selectOption('07');
  await selectsInicio.nth(1).selectOption('45');
  await Promise.all([
    page.waitForResponse(resp => resp.url().includes('/api/actas-comite/') && resp.request().method() === 'PUT'),
    selectsInicio.nth(2).selectOption('p.m.'),
  ]);

  await page.reload();
  await page.getByRole('button', { name: '1. Orden del día' }).click();
  const selectsInicioTrasReload = page.locator('.form-field', { hasText: 'Hora de inicio' }).locator('app-time-select-12h select');
  await expect(selectsInicioTrasReload.nth(0)).toHaveValue('07');
  await expect(selectsInicioTrasReload.nth(1)).toHaveValue('45');
  await expect(selectsInicioTrasReload.nth(2)).toHaveValue('p.m.');

  // --- Tab 5: Termina la reunión a las... (caso borde: 12 a. m. = 00:00) ---
  await page.getByRole('button', { name: '5. Observaciones' }).click();
  const campoFin = page.locator('.form-field', { hasText: 'Termina la reunión' });
  await expect(campoFin.locator('input[type="time"]')).toHaveCount(0);
  const selectsFin = campoFin.locator('app-time-select-12h select');

  await selectsFin.nth(0).selectOption('12');
  await selectsFin.nth(1).selectOption('00');
  await Promise.all([
    page.waitForResponse(resp => resp.url().includes('/api/actas-comite/') && resp.request().method() === 'PUT'),
    selectsFin.nth(2).selectOption('a.m.'),
  ]);

  await page.reload();
  await page.getByRole('button', { name: '5. Observaciones' }).click();
  const selectsFinTrasReload = page.locator('.form-field', { hasText: 'Termina la reunión' }).locator('app-time-select-12h select');
  await expect(selectsFinTrasReload.nth(0)).toHaveValue('12');
  await expect(selectsFinTrasReload.nth(1)).toHaveValue('00');
  await expect(selectsFinTrasReload.nth(2)).toHaveValue('a.m.');
});

test('SCRUM-278: campos de Monto quedan alineados a la derecha (Decisión y Resumen)', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'coordinador_comercial');

  await page.goto('/actas-comite');
  // El acta del test anterior ya existe y se retoma — para cuando llega acá
  // ya no está en 'pendiente' sino en 'borrador' (la 1ª edición de un campo
  // la transiciona sola), así que se ubica por el botón de acción
  // (Elaborar/Continuar), no por el texto de estado en la fila.
  const filaPendiente = page.locator('tr').filter({ has: page.getByRole('button', { name: /Elaborar|Continuar/i }) }).first();
  await filaPendiente.getByRole('button', { name: /Elaborar|Continuar/i }).click();
  await expect.poll(() => page.url(), { timeout: 10000 }).toMatch(/\/actas-comite\/\d+$/);

  await page.getByRole('button', { name: '3. Decisión' }).click();
  const card = page.locator('.solicitud-detalle-card').filter({ hasText: 'Cliente Playwright SC278' });
  const inputMonto = card.locator('.form-field', { hasText: 'Monto' }).locator('input');
  await expect(inputMonto).toHaveClass(/text-right/);
  await expect(inputMonto).toHaveCSS('text-align', 'right');

  await page.getByRole('button', { name: '4. Resumen' }).click();
  const filaResumen = page.locator('tbody tr', { hasText: 'Cliente Playwright SC278' });
  const inputDecision = filaResumen.locator('input');
  await expect(inputDecision).toHaveClass(/text-right/);
  await expect(inputDecision).toHaveCSS('text-align', 'right');
});
