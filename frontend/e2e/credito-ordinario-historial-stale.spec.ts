import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-176 — el "Historial de Auditoría y Trazabilidad" de Crédito Ordinario
 * (y de Informe Técnico) se cargaba una sola vez por instancia de componente.
 * Si el usuario volvía a la pantalla con atrás/adelante del navegador
 * (bfcache) o cambiando de pestaña y regresando, Angular no volvía a correr
 * ngOnInit ni disparaba ninguna petición nueva: la vista quedaba congelada
 * con lo último cargado, aunque el backend ya tuviera transiciones nuevas
 * (SARLAFT, Análisis Financiero) escritas por otro módulo/pestaña — el
 * síntoma exacto reportado por Juan (BD completa, pantalla mostrando solo
 * el primer evento). El fix agrega listeners de `pageshow` (persisted) y
 * `visibilitychange` que recargan el crédito activo.
 *
 * Este test no puede pasar por "Registro Solicitud de Crédito" para crear el
 * crédito de prueba (bug no relacionado, ver SCRUM-185: registrar un cliente
 * nuevo desde ese formulario falla con 404) — crea el crédito directo por
 * tinker, igual que se hizo para validar el fix manualmente.
 *
 * Requiere ng serve (localhost:4200) + backend Docker (localhost:8000)
 * corriendo, con el cliente id=1 sembrado (ver otros specs de este directorio).
 */

function tinker(php: string): string {
  const fs = require('fs');
  const tmp = '/tmp/_scrum176_e2e_tinker.php';
  fs.writeFileSync(tmp, php);
  return execSync(`docker exec -i factoring_backend php artisan tinker < ${tmp}`).toString();
}

test.describe('Historial de Auditoría no queda congelado tras acción cruzada (SCRUM-176)', () => {
  let creditoId: number;

  test.beforeAll(() => {
    const out = tinker(`
      $c = \\App\\Models\\CreditoOrdinario::iniciar(
        clienteId: 1, monto: 5000000, plazoMeses: 12,
        usuario: 'E2E SCRUM-176', rol: 'coordinador_comercial',
        comentario: 'E2E SCRUM-176 - historial stale fix'
      );
      echo 'ID:'.$c->id.':ESTADO:'.$c->estado.':HIST:'.count($c->historial_estados ?? []);
    `);
    const match = out.match(/ID:(\d+):ESTADO:(\w+):HIST:(\d+)/);
    if (!match) throw new Error('No se pudo crear el crédito de prueba vía tinker: ' + out);
    creditoId = Number(match[1]);
    console.log(`  [beforeAll] crédito creado id=${creditoId} estado=${match[2]} historial=${match[3]}`);
  });

  test.afterAll(() => {
    tinker(`\\App\\Models\\CreditoOrdinario::find(${creditoId})?->delete(); echo 'ok';`);
  });

  test('un pageshow (persisted) refresca el historial con transiciones escritas en otro módulo', async ({ page }) => {
    page.on('response', async res => {
      if (res.url().includes('/api/creditos') && res.request().method() === 'GET') {
        const body = await res.json().catch(() => null);
        console.log(`  [GET /creditos] status=${res.status()} count=${Array.isArray(body) ? body.length : body} incluyeCreditoId=${Array.isArray(body) ? body.some((c: any) => c.id === creditoId) : 'n/a'}`);
      }
    });

    await loginAs(page, '1234', '1234', 'coordinador_comercial');
    // SCRUM-176: entra directo con el id en la URL — con el fix, ya no hace
    // falta (ni es fiable) intentar seleccionar el crédito de prueba
    // haciendo click por texto en el sidebar, cuyo campo visible es el
    // cliente/número de solicitud, no el "usuario" del historial.
    await page.goto(`/creditos/${creditoId}`, { waitUntil: 'networkidle' });
    await expect(page.locator('h1', { hasText: 'Crédito Ordinario' })).toBeVisible({ timeout: 10000 });

    const countBefore = await page.locator('.timeline-item').count();
    expect(countBefore).toBeGreaterThan(0);

    // Simula una acción en OTRO módulo (SARLAFT) escribiendo directo en BD,
    // sin que esta pestaña haga ninguna petición nueva todavía.
    tinker(`
      $c = \\App\\Models\\CreditoOrdinario::find(${creditoId});
      $h = $c->historial_estados ?? [];
      $h[] = ['fecha'=>now()->toIso8601String(),'usuario'=>'E2E','rol'=>'oficial_cumplimiento','estado_anterior'=>$c->estado,'estado_nuevo'=>'pendiente_analisis_financiero','comentario'=>'E2E: concepto SARLAFT favorable'];
      $c->estado = 'pendiente_analisis_financiero';
      $c->historial_estados = $h;
      $c->save();
      echo 'ok';
    `);

    // Sin ningún evento todavía, la vista sigue congelada (documenta el bug
    // si el fix se revirtiera).
    expect(await page.locator('.timeline-item').count()).toBe(countBefore);

    // Dispara el evento que el fix escucha (equivalente real: volver con
    // atrás/adelante del navegador después de resolver SARLAFT en otra
    // pestaña).
    await page.evaluate(() => {
      window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
    });

    await expect
      .poll(() => page.locator('.timeline-item').count(), { timeout: 5000 })
      .toBe(countBefore + 1);
    await expect(page.locator('.timeline-item').last()).toContainText('SARLAFT favorable');
  });
});

/**
 * SCRUM-176 (re-investigación 2026-08-07) — el bfcache fix de arriba no era
 * suficiente: SARLAFT/Análisis Financiero/Informe Técnico son rutas Angular
 * separadas, así que volver a "Mis Créditos" ya destruye y recrea el
 * componente (dispara ngOnInit solo, sin necesitar el listener). La causa
 * raíz real era que `credito-ordinario.component.ts` no tenía ninguna
 * identidad de "qué crédito estoy viendo" — sin id en la URL, `loadCreditos()`
 * caía en `data[0]`, el crédito más recién creado por cualquiera en el
 * sistema. En un entorno con más de un crédito de prueba, esto mostraba en
 * silencio la trazabilidad de un crédito distinto al que el usuario venía
 * revisando — reproduce exactamente "se ve estancada en el mismo punto" en
 * cada repro nueva de Juan (CO-2026-SSYZ15, CO-2026-XFMB41), porque cada vez
 * había un crédito más nuevo de por medio.
 */
test.describe('Selección de crédito no cae al más reciente por error al volver de otra ruta (SCRUM-176)', () => {
  let creditoViejoId: number;
  let creditoNuevoId: number;
  let numeroSolicitudViejo: string;

  test.beforeAll(() => {
    const crear = (marca: string) => {
      const out = tinker(`
        $c = \\App\\Models\\CreditoOrdinario::iniciar(
          clienteId: 1, monto: 5000000, plazoMeses: 12,
          usuario: '${marca}', rol: 'coordinador_comercial',
          comentario: '${marca}'
        );
        $h = $c->historial_estados ?? [];
        $h[] = ['fecha'=>now()->toIso8601String(),'usuario'=>'E2E','rol'=>'oficial_cumplimiento','estado_anterior'=>$c->estado,'estado_nuevo'=>'pendiente_analisis_financiero','comentario'=>'${marca}: paso SARLAFT'];
        $c->estado = 'pendiente_analisis_financiero';
        $c->historial_estados = $h;
        $c->save();
        echo 'ID:'.$c->id.':NUM:'.$c->numero_solicitud;
      `);
      const match = out.match(/ID:(\d+):NUM:(\S+)/);
      if (!match) throw new Error('No se pudo crear el crédito de prueba vía tinker: ' + out);
      return { id: Number(match[1]), numeroSolicitud: match[2] };
    };

    // El "viejo" se crea primero (es el que el usuario está revisando); el
    // "nuevo" se crea después, simulando otro crédito de prueba que aparece
    // en el sistema mientras tanto (el escenario real reportado por Juan).
    const viejo = crear('E2E SCRUM-176 VIEJO');
    creditoViejoId = viejo.id;
    numeroSolicitudViejo = viejo.numeroSolicitud;
    creditoNuevoId = crear('E2E SCRUM-176 NUEVO').id;
  });

  test.afterAll(() => {
    tinker(`\\App\\Models\\CreditoOrdinario::find(${creditoViejoId})?->delete(); \\App\\Models\\CreditoOrdinario::find(${creditoNuevoId})?->delete(); echo 'ok';`);
  });

  test('volver a /creditos sin id no muestra en silencio el crédito más reciente', async ({ page }) => {
    await loginAs(page, '1234', '1234', 'coordinador_comercial');

    // El usuario abre el crédito viejo (2 pasos de historial: iniciar + SARLAFT).
    await page.goto(`/creditos/${creditoViejoId}`, { waitUntil: 'networkidle' });
    await expect(page.locator('.timeline-item')).toHaveCount(2, { timeout: 10000 });

    // Navega a otra ruta (SARLAFT, ruta Angular separada) y vuelve a la
    // pantalla genérica de "Mis Créditos" sin id — como haría el sidebar.
    await page.goto(`/listas-sarlaft/${creditoViejoId}`, { waitUntil: 'networkidle' });
    await page.goto('/creditos', { waitUntil: 'networkidle' });

    // No debe adivinar ningún crédito (ni el viejo ni el nuevo): pide elegir.
    await expect(page.locator('h3', { hasText: 'Selecciona una solicitud' })).toBeVisible({ timeout: 10000 });
    await expect(page.locator('.timeline-item')).toHaveCount(0);

    // Al elegir explícitamente el crédito viejo (por su número de solicitud,
    // el único campo del sidebar que lo identifica de forma única), muestra
    // SU historial (no el del nuevo) y la URL queda con su id.
    await page.locator('.credit-item-card', { hasText: numeroSolicitudViejo }).first().click();
    await expect(page).toHaveURL(new RegExp(`/creditos/${creditoViejoId}$`));
    await expect(page.locator('.timeline-item')).toHaveCount(2, { timeout: 10000 });
    await expect(page.locator('.timeline-item').last()).toContainText('VIEJO');
  });

  test('entrar directo a /creditos/:id muestra ese crédito aunque exista uno más reciente', async ({ page }) => {
    await loginAs(page, '1234', '1234', 'coordinador_comercial');
    await page.goto(`/creditos/${creditoViejoId}`, { waitUntil: 'networkidle' });

    await expect(page.locator('.timeline-item')).toHaveCount(2, { timeout: 10000 });
    await expect(page.locator('.timeline-item').last()).toContainText('VIEJO');
  });
});
