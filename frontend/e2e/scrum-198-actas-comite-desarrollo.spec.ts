import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-198 (2026-08-16) — Acta de Comité, pestaña Desarrollo:
 * 1. El Orden del día por defecto de un acta nueva trae 5 ítems, no 7 (se
 *    quitaron "Presentación de solicitudes de crédito." y "Decisión de
 *    solicitudes presentadas.").
 * 2. El buscador de créditos elegibles no ofrece un crédito que no está en
 *    comite_evaluacion (camino de ruptura).
 *
 * El flujo feliz del buscador (buscar → seleccionar → aparece en la tabla
 * con origen "Existente", ya no hay formulario libre) queda cubierto en
 * scrum-189-190-actas-gestion-creditos.spec.ts, que ya ejercita ese camino
 * completo — no se duplica acá.
 */

function tinker(php: string): string {
  const fs = require('fs');
  const tmp = '/tmp/_scrum198_desarrollo_e2e_tinker.php';
  fs.writeFileSync(tmp, php);
  return execSync(`docker exec -i factoring_backend php artisan tinker < ${tmp}`).toString();
}

test.beforeAll(() => {
  // Limpia cualquier acta pendiente/borrador de una corrida previa
  // interrumpida — "Generar acta pendiente" está bloqueado mientras exista
  // una (regla de concurrencia real del módulo).
  tinker(`
    \\App\\Models\\ActaComite::whereIn('estado', ['pendiente', 'borrador'])->get()->each(function ($a) {
      \\App\\Models\\ActaComiteSolicitud::where('acta_comite_id', $a->id)->delete();
      $a->delete();
    });
    \\App\\Models\\CreditoOrdinario::where('numero_solicitud', 'like', 'SC198-%')->delete();

    $docCC = \\App\\Models\\DocumentType::firstOrCreate(['codigo' => 'CC'], ['nombre' => 'Cédula']);
    $tipoNatural = \\App\\Models\\TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
    $tipoOrdinario = \\App\\Models\\TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
    $amort = \\App\\Models\\Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);
    $admin = \\App\\Models\\User::where('numero_documento', '1234')->firstOrFail();

    $cliente = \\App\\Models\\Cliente::firstOrCreate(
      ['numero_documento' => '900555666'],
      ['tipo_persona_id' => $tipoNatural->id, 'tipo_documento_id' => $docCC->id,
       'identificacion' => '900555666', 'nombre' => 'Cliente Playwright SC198',
       'nombres' => 'Cliente', 'primer_apellido' => 'Playwright SC198',
       'correo_electronico' => 'sc198@test.com', 'telefono' => '3000000009',
       'direccion' => 'Calle SC198 1', 'pais' => 'Colombia', 'departamento' => 'Valle',
       'ciudad' => 'Cali', 'activo' => true]
    );

    // Elegible: aparece para generar el acta con el orden del día por defecto.
    $solicitudElegible = \\App\\Models\\SolicitudCredito::create([
      'cliente_id' => $cliente->id, 'usuario_registra_id' => $admin->id,
      'tipo_credito_id' => $tipoOrdinario->id, 'monto_solicitado' => 10000000, 'plazo_meses' => 12,
      'amortizacion_id' => $amort->id, 'destino_recurso' => 'Capital de trabajo',
      'garantia' => 'Pagaré', 'fuente_pago' => 'Ingresos operacionales',
      'correo_notificacion' => 'sc198@test.com', 'asunto_notificacion' => 'Doc',
      'mensaje_notificacion' => 'Adjunta.',
    ]);
    \\App\\Models\\CreditoOrdinario::create([
      'numero_solicitud' => 'SC198-ELEGIBLE', 'cliente_id' => $admin->id,
      'solicitud_credito_id' => $solicitudElegible->id, 'monto' => 10000000, 'plazo_meses' => 12,
      'estado' => 'comite_evaluacion', 'documentos' => [],
    ]);

    // No elegible: mismo cliente, pero ya aprobado — no debe aparecer en el buscador.
    $solicitudNoElegible = \\App\\Models\\SolicitudCredito::create([
      'cliente_id' => $cliente->id, 'usuario_registra_id' => $admin->id,
      'tipo_credito_id' => $tipoOrdinario->id, 'monto_solicitado' => 8000000, 'plazo_meses' => 12,
      'amortizacion_id' => $amort->id, 'destino_recurso' => 'Capital de trabajo',
      'garantia' => 'Pagaré', 'fuente_pago' => 'Ingresos operacionales',
      'correo_notificacion' => 'sc198@test.com', 'asunto_notificacion' => 'Doc',
      'mensaje_notificacion' => 'Adjunta.',
    ]);
    \\App\\Models\\CreditoOrdinario::create([
      'numero_solicitud' => 'SC198-NO-ELEGIBLE', 'cliente_id' => $admin->id,
      'solicitud_credito_id' => $solicitudNoElegible->id, 'monto' => 8000000, 'plazo_meses' => 12,
      'estado' => 'aprobada_garantias', 'resultado_origen' => 'comite_aprobado', 'documentos' => [],
    ]);

    echo 'OK';
  `);
});

test('acta nueva trae 5 ítems en el orden del día (sin los 2 quitados por SCRUM-198)', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'coordinador_comercial');

  await page.goto('/actas-comite');
  await page.getByRole('button', { name: /Generar acta pendiente/i }).click();
  await page.getByRole('button', { name: 'OK' }).click();
  await expect.poll(() => page.url(), { timeout: 10000 }).toMatch(/\/actas-comite\/\d+$/);

  await page.getByRole('button', { name: '1. Orden del día' }).click();
  await expect(page.getByText('Presentación de solicitudes de crédito.')).toHaveCount(0);
  await expect(page.getByText('Decisión de solicitudes presentadas.')).toHaveCount(0);
  await expect(page.getByText('Revisión de atribuciones en las operaciones')).toBeVisible();

  await expect(page.locator('.orden-dia-item')).toHaveCount(5);
});

test('el buscador de créditos elegibles no ofrece un crédito fuera de comite_evaluacion', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'coordinador_comercial');

  const respuestaActas = page.waitForResponse(resp => resp.url().includes('/api/actas-comite') && resp.request().method() === 'GET');
  await page.goto('/actas-comite');
  await respuestaActas;
  const filaPendiente = page.locator('tr', { hasText: 'Pendiente' }).first();
  await filaPendiente.waitFor({ state: 'attached', timeout: 3000 }).catch(() => {});
  if (await filaPendiente.count()) {
    await filaPendiente.getByRole('button', { name: /Elaborar|Continuar/i }).click();
  } else {
    await page.getByRole('button', { name: /Generar acta pendiente/i }).click();
    await page.getByRole('button', { name: 'OK' }).click();
  }
  await expect.poll(() => page.url(), { timeout: 10000 }).toMatch(/\/actas-comite\/\d+$/);

  // Este test corre después del anterior, que ya generó el acta y esa
  // acta ya incluyó SC198-ELEGIBLE automáticamente como origen 'sistema'
  // (sincronizarSolicitudesElegibles) — al buscar "Cliente Playwright
  // SC198" acá no debería quedar NINGÚN crédito ofrecible: ni el ya
  // incluido, ni el no elegible (aprobada_garantias).
  await page.getByRole('button', { name: '2. Desarrollo' }).click();
  await page.locator('.credito-elegible-autocomplete input').fill('Cliente Playwright SC198');

  await expect(page.getByText('Sin créditos elegibles que coincidan.')).toBeVisible({ timeout: 5000 });
  await expect(page.locator('.credito-elegible-autocomplete-suggestions li')).toHaveCount(0);
});
