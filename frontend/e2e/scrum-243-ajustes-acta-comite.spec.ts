import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-243 (2026-08-24) — Ajustes: Tercera entrega crédito, Acta de Comité
 * de Crédito, pestaña Decisión y Observaciones:
 * 1. Se quita el campo "% Financiación" de la pestaña Decisión.
 * 2. El estado "Rechazado" pasa a mostrarse como "Negado" (solo la etiqueta
 *    visible — el valor interno guardado sigue siendo 'rechazado', ver
 *    labelEstadoDecision() en actas-comite-detalle.component.ts).
 * 3. El campo "Fuente de pago" pasa de <input> a <textarea> más ancho (2
 *    columnas del grid), admite texto extenso sin recortarse.
 * 4. El editor de "Observaciones generales" (pestaña 5) deja de ser angosto
 *    (mismo bug de quill-editor inline-block que SCRUM-198 en Desarrollo).
 */

function tinker(php: string): string {
  const fs = require('fs');
  const tmp = '/tmp/_scrum243_e2e_tinker.php';
  fs.writeFileSync(tmp, php);
  return execSync(`docker exec -i factoring_backend php artisan tinker < ${tmp}`).toString();
}

test.beforeAll(() => {
  // Limpia cualquier acta pendiente/borrador de una corrida previa
  // interrumpida — "Generar acta pendiente" está bloqueado mientras exista
  // una (mismo mecanismo que SCRUM-198).
  tinker(`
    \\App\\Models\\ActaComite::whereIn('estado', ['pendiente', 'borrador'])->get()->each(function ($a) {
      \\App\\Models\\ActaComiteSolicitud::where('acta_comite_id', $a->id)->delete();
      $a->delete();
    });
    \\App\\Models\\CreditoOrdinario::where('numero_solicitud', 'SC243-FUENTE-PAGO')->delete();

    $docCC = \\App\\Models\\DocumentType::firstOrCreate(['codigo' => 'CC'], ['nombre' => 'Cédula']);
    $tipoNatural = \\App\\Models\\TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
    $tipoOrdinario = \\App\\Models\\TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
    $amort = \\App\\Models\\Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);
    $admin = \\App\\Models\\User::where('numero_documento', '1234')->firstOrFail();

    $cliente = \\App\\Models\\Cliente::firstOrCreate(
      ['numero_documento' => '900777888'],
      ['tipo_persona_id' => $tipoNatural->id, 'tipo_documento_id' => $docCC->id,
       'identificacion' => '900777888', 'nombre' => 'Cliente Playwright SC243',
       'nombres' => 'Cliente', 'primer_apellido' => 'Playwright SC243',
       'correo_electronico' => 'sc243@test.com', 'telefono' => '3000000010',
       'direccion' => 'Calle SC243 1', 'pais' => 'Colombia', 'departamento' => 'Valle',
       'ciudad' => 'Cali', 'activo' => true]
    );

    $fuentePagoLarga = 'Ingresos operacionales del negocio de comercialización de insumos agrícolas, complementados con arriendos de bodega y rendimientos financieros de CDT vigentes';

    $solicitud = \\App\\Models\\SolicitudCredito::create([
      'cliente_id' => $cliente->id, 'usuario_registra_id' => $admin->id,
      'tipo_credito_id' => $tipoOrdinario->id, 'monto_solicitado' => 15000000, 'plazo_meses' => 12,
      'amortizacion_id' => $amort->id, 'destino_recurso' => 'Capital de trabajo',
      'garantia' => 'Pagaré', 'fuente_pago' => $fuentePagoLarga,
      'correo_notificacion' => 'sc243@test.com', 'asunto_notificacion' => 'Doc',
      'mensaje_notificacion' => 'Adjunta.',
    ]);
    \\App\\Models\\CreditoOrdinario::create([
      'numero_solicitud' => 'SC243-FUENTE-PAGO', 'cliente_id' => $admin->id,
      'solicitud_credito_id' => $solicitud->id, 'monto' => 15000000, 'plazo_meses' => 12,
      'estado' => 'comite_evaluacion', 'documentos' => [],
    ]);

    echo 'OK';
  `);
});

test('Decisión: sin % Financiación, Fuente de pago ancha, Estado dice NEGADO', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'coordinador_comercial');

  await page.goto('/actas-comite');
  await page.getByRole('button', { name: /Generar acta pendiente/i }).click();
  await page.getByRole('button', { name: 'OK' }).click();
  await expect.poll(() => page.url(), { timeout: 10000 }).toMatch(/\/actas-comite\/\d+$/);

  // El crédito sembrado en beforeAll ya estaba en comite_evaluacion antes de
  // generar el acta, así que sincronizarSolicitudesElegibles() lo incluyó
  // solo (origen "sistema") — no hace falta buscarlo, ya aparece en Decisión.
  await page.getByRole('button', { name: '3. Decisión' }).click();
  const card = page.locator('.solicitud-detalle-card').filter({ hasText: 'Cliente Playwright SC243' });

  // Punto 1: "% Financiación" ya no está en la pestaña Decisión.
  await expect(page.getByText('% Financiación')).toHaveCount(0);

  // Punto 3: "Fuente de pago" es un textarea (no input) con el valor
  // completo, sin recortar.
  const campoFuentePago = card.locator('.form-field', { hasText: 'Fuente de pago' });
  await expect(campoFuentePago.locator('input')).toHaveCount(0);
  const textareaFuentePago = campoFuentePago.locator('textarea');
  await expect(textareaFuentePago).toHaveValue(/Ingresos operacionales del negocio de comercialización/);
  // El campo debe ocupar 2 columnas del grid — su ancho es notablemente
  // mayor que el de un campo de 1 columna vecino (ej. "Garantías").
  const anchoFuentePago = await textareaFuentePago.boundingBox();
  const anchoGarantias = await card.locator('.form-field', { hasText: 'Garantías' }).locator('input').boundingBox();
  expect(anchoFuentePago!.width).toBeGreaterThan(anchoGarantias!.width * 1.5);

  // Punto 2: el dropdown de Estado ya no ofrece "RECHAZADO", ofrece "NEGADO"
  // (el <option value="rechazado"> se mantiene igual, solo cambia el texto).
  const selectEstado = card.locator('.form-field', { hasText: 'Estado' }).locator('select');
  await expect(selectEstado.locator('option', { hasText: 'RECHAZADO' })).toHaveCount(0);
  await expect(selectEstado.locator('option', { hasText: 'NEGADO' })).toHaveCount(1);
  await selectEstado.selectOption('rechazado');
  await selectEstado.blur();

  // --- Resumen: badge y total dicen "NEGADO", no "RECHAZADO" ---
  await page.getByRole('button', { name: '4. Resumen' }).click();
  await expect(page.getByText('RECHAZADO', { exact: true })).toHaveCount(0);
  await expect(page.getByText('NEGADO', { exact: true })).toBeVisible();
  await expect(page.getByText('Total Rechazado')).toHaveCount(0);
  await expect(page.getByText('Total Negado')).toBeVisible();
});

test('Observaciones: el editor de texto enriquecido ocupa el ancho completo', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'coordinador_comercial');

  await page.goto('/actas-comite');
  // El acta pendiente ya existe (creada por el test anterior) — se retoma.
  const filaPendiente = page.locator('tr', { hasText: 'Pendiente' }).first();
  await filaPendiente.getByRole('button', { name: /Elaborar|Continuar/i }).click();
  await expect.poll(() => page.url(), { timeout: 10000 }).toMatch(/\/actas-comite\/\d+$/);

  await page.getByRole('button', { name: '5. Observaciones' }).click();

  const contenedor = page.locator('.observaciones-generales-editor');
  const editor = contenedor.locator('quill-editor');
  await expect(editor).toBeVisible();

  const anchoContenedor = (await contenedor.boundingBox())!.width;
  const anchoEditor = (await editor.boundingBox())!.width;
  // Antes del fix el quill-editor era inline-block y quedaba angosto
  // (ajustado a su contenido); ahora debe ocupar prácticamente todo el
  // ancho de su contenedor de bloque.
  expect(anchoEditor).toBeGreaterThan(anchoContenedor * 0.95);

  const altoQlEditor = (await page.locator('.observaciones-generales-editor .ql-editor').boundingBox())!.height;
  expect(altoQlEditor).toBeGreaterThanOrEqual(200);
});
