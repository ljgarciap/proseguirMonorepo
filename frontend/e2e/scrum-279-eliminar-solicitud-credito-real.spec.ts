import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-279 (2026-08-27) — Acta de Comité, pestaña "Desarrollo" ›
 * "Presentación de solicitudes": el botón "Eliminar" solo existía para
 * origen 'manual' — para créditos reales (origen 'sistema'/'manual_existente',
 * que es justo lo que Juan Andrés marcó en la captura) nunca apareció.
 * Decisión de Luis: se puede eliminar, pero SOLO se excluye de esta acta
 * puntual (el crédito sigue en comite_evaluacion, elegible para una futura
 * acta) — no se le cambia el estado. Se puede volver a agregar a mano desde
 * el buscador si fue un error.
 */
function tinker(php: string): string {
  const fs = require('fs');
  const tmp = '/tmp/_scrum279_e2e_tinker.php';
  fs.writeFileSync(tmp, php);
  return execSync(`docker exec -i factoring_backend php artisan tinker < ${tmp}`).toString();
}

let creditoId: string;

test.beforeAll(() => {
  const salida = tinker(`
    \\App\\Models\\ActaComite::whereIn('estado', ['pendiente', 'borrador'])->get()->each(function ($a) {
      \\App\\Models\\ActaComiteSolicitud::where('acta_comite_id', $a->id)->delete();
      $a->delete();
    });
    \\App\\Models\\CreditoOrdinario::where('numero_solicitud', 'SC279-EXCLUIR')->delete();

    $docCC = \\App\\Models\\DocumentType::firstOrCreate(['codigo' => 'CC'], ['nombre' => 'Cédula']);
    $tipoNatural = \\App\\Models\\TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
    $tipoOrdinario = \\App\\Models\\TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
    $amort = \\App\\Models\\Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);
    $admin = \\App\\Models\\User::where('numero_documento', '1234')->firstOrFail();

    $cliente = \\App\\Models\\Cliente::firstOrCreate(
      ['numero_documento' => '900777902'],
      ['tipo_persona_id' => $tipoNatural->id, 'tipo_documento_id' => $docCC->id,
       'identificacion' => '900777902', 'nombre' => 'Cliente Playwright SC279',
       'nombres' => 'Cliente', 'primer_apellido' => 'Playwright SC279',
       'correo_electronico' => 'sc279@test.com', 'telefono' => '3000000013',
       'direccion' => 'Calle SC279 1', 'pais' => 'Colombia', 'departamento' => 'Valle',
       'ciudad' => 'Cali', 'activo' => true]
    );
    $solicitud = \\App\\Models\\SolicitudCredito::create([
      'cliente_id' => $cliente->id, 'usuario_registra_id' => $admin->id,
      'tipo_credito_id' => $tipoOrdinario->id, 'monto_solicitado' => 9000000, 'plazo_meses' => 12,
      'amortizacion_id' => $amort->id, 'destino_recurso' => 'Capital de trabajo',
      'garantia' => 'Pagaré', 'fuente_pago' => 'Ventas del negocio',
      'correo_notificacion' => 'sc279@test.com', 'asunto_notificacion' => 'Doc',
      'mensaje_notificacion' => 'Adjunta.',
    ]);
    $credito = \\App\\Models\\CreditoOrdinario::create([
      'numero_solicitud' => 'SC279-EXCLUIR', 'cliente_id' => $admin->id,
      'solicitud_credito_id' => $solicitud->id, 'monto' => 9000000, 'plazo_meses' => 12,
      'estado' => 'comite_evaluacion', 'documentos' => [],
    ]);

    echo 'CREDITO_ID=' . $credito->id;
  `);

  const match = salida.match(/CREDITO_ID=(\d+)/);
  if (!match) throw new Error('No se pudo sembrar el crédito: ' + salida);
  creditoId = match[1];
});

test.afterAll(() => {
  tinker(`
    \\App\\Models\\ActaComite::whereIn('estado', ['pendiente', 'borrador'])->get()->each(function ($a) {
      \\App\\Models\\ActaComiteSolicitud::where('acta_comite_id', $a->id)->delete();
      $a->delete();
    });
    $c = \\App\\Models\\CreditoOrdinario::where('numero_solicitud', 'SC279-EXCLUIR')->first();
    if ($c) {
      $sid = $c->solicitud_credito_id;
      $c->delete();
      if ($sid) \\App\\Models\\SolicitudCredito::where('id', $sid)->delete();
    }
    \\App\\Models\\Cliente::where('numero_documento', '900777902')->delete();
    echo 'OK';
  `);
});

test('eliminar una solicitud de crédito real la excluye de esta acta, sin cambiar su estado, y no reaparece al recargar', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'coordinador_comercial');

  await page.goto('/actas-comite');
  await page.getByRole('button', { name: /Generar acta pendiente/i }).click();
  await page.getByRole('button', { name: 'OK' }).click();
  await expect.poll(() => page.url(), { timeout: 10000 }).toMatch(/\/actas-comite\/\d+$/);

  await page.getByRole('button', { name: '2. Desarrollo' }).click();
  const fila = page.locator('tr').filter({ hasText: 'Cliente Playwright SC279' });
  await expect(fila).toBeVisible();
  await expect(fila.getByText('Análisis financiero')).toBeVisible();

  const botonEliminar = fila.getByRole('button', { name: 'Eliminar' });
  await expect(botonEliminar).toBeVisible(); // antes del fix, no existía para origen 'sistema'
  await botonEliminar.click();

  // El diálogo aclara que el crédito no se borra, solo se excluye de esta acta.
  await expect(page.getByText(/no se elimina ni cambia de estado/i)).toBeVisible();
  // Scoped al popup: la fila (todavía en el DOM) tiene su propio botón
  // "Eliminar" con el mismo texto que el de confirmación del Swal.
  await page.locator('.swal2-popup').getByRole('button', { name: 'Eliminar' }).click();

  await expect(page.locator('tr').filter({ hasText: 'Cliente Playwright SC279' })).toHaveCount(0);

  // Recargar (dispara show() -> sincronizarSolicitudesElegibles()) no la trae de vuelta.
  await page.reload();
  await page.getByRole('button', { name: '2. Desarrollo' }).click();
  await expect(page.locator('tr').filter({ hasText: 'Cliente Playwright SC279' })).toHaveCount(0);

  // El crédito real sigue en comite_evaluacion — no se le cambió el estado.
  const estado = execSync(
    `docker exec -i factoring_backend php artisan tinker --execute="echo \\App\\Models\\CreditoOrdinario::find(${creditoId})->estado;"`
  ).toString();
  expect(estado).toContain('comite_evaluacion');

  // Se puede volver a agregar a mano desde el buscador.
  await page.locator('input[placeholder*="Buscar crédito elegible"]').fill('Playwright SC279');
  await expect(page.getByText('Cliente Playwright SC279')).toBeVisible({ timeout: 5000 });
  await page.getByText('Cliente Playwright SC279').first().click();
  await expect(page.locator('tr').filter({ hasText: 'Cliente Playwright SC279' })).toBeVisible();
  await expect(page.locator('tr').filter({ hasText: 'Cliente Playwright SC279' }).getByText('Existente (agregado manualmente)')).toBeVisible();
});
