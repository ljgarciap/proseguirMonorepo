<?php

namespace Tests\Feature;

use App\Models\ActaComite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SCRUM-198: migración retroactiva que quita del Orden del día los ítems
 * "Presentación de solicitudes de crédito." y "Decisión de solicitudes
 * presentadas." de Actas no registradas (pendiente/borrador), salvo que ya
 * tengan contenido escrito en Desarrollo.
 */
class RemoveOrdenDiaItemsScrum198Test extends TestCase
{
    use RefreshDatabase;

    private function ordenDiaConItemsObjetivo(): array
    {
        return [
            ['id' => 1, 'texto' => 'Verificación de quórum.', 'orden' => 1],
            ['id' => 6, 'texto' => 'Presentación de solicitudes de crédito.', 'orden' => 6],
            ['id' => 7, 'texto' => 'Decisión de solicitudes presentadas.', 'orden' => 7],
        ];
    }

    public function test_quita_items_objetivo_sin_contenido_de_acta_borrador(): void
    {
        $acta = ActaComite::create([
            'numero' => 1,
            'estado' => 'borrador',
            'asistentes' => [],
            'orden_dia' => $this->ordenDiaConItemsObjetivo(),
            'desarrollo' => [],
            'firmantes' => [],
        ]);

        $this->artisan('app:scrum-198-quitar-items-orden-dia')->assertExitCode(0);

        $acta->refresh();
        $textos = collect($acta->orden_dia)->pluck('texto');
        $this->assertTrue($textos->contains('Verificación de quórum.'));
        $this->assertFalse($textos->contains('Presentación de solicitudes de crédito.'));
        $this->assertFalse($textos->contains('Decisión de solicitudes presentadas.'));
        $this->assertCount(1, $acta->orden_dia);
    }

    public function test_conserva_item_objetivo_con_contenido_ya_escrito(): void
    {
        $acta = ActaComite::create([
            'numero' => 1,
            'estado' => 'borrador',
            'asistentes' => [],
            'orden_dia' => $this->ordenDiaConItemsObjetivo(),
            // El ítem 6 ya tiene contenido escrito por un Coordinador — no debe borrarse.
            'desarrollo' => ['6' => '<p>El comité ya presentó 3 solicitudes.</p>'],
            'firmantes' => [],
        ]);

        $this->artisan('app:scrum-198-quitar-items-orden-dia')->assertExitCode(0);

        $acta->refresh();
        $textos = collect($acta->orden_dia)->pluck('texto');
        $this->assertTrue($textos->contains('Presentación de solicitudes de crédito.'), 'tiene contenido, debe conservarse');
        $this->assertFalse($textos->contains('Decisión de solicitudes presentadas.'), 'sin contenido, debe quitarse');
        $this->assertArrayHasKey('6', $acta->desarrollo);
        $this->assertCount(2, $acta->orden_dia);
    }

    public function test_no_toca_acta_ya_aprobada(): void
    {
        $acta = ActaComite::create([
            'numero' => 1,
            'estado' => 'aprobada',
            'asistentes' => [],
            'orden_dia' => $this->ordenDiaConItemsObjetivo(),
            'desarrollo' => [],
            'firmantes' => [],
        ]);

        $this->artisan('app:scrum-198-quitar-items-orden-dia')->assertExitCode(0);

        $acta->refresh();
        $this->assertCount(3, $acta->orden_dia);
    }

    public function test_dry_run_no_persiste_cambios(): void
    {
        $acta = ActaComite::create([
            'numero' => 1,
            'estado' => 'pendiente',
            'asistentes' => [],
            'orden_dia' => $this->ordenDiaConItemsObjetivo(),
            'desarrollo' => [],
            'firmantes' => [],
        ]);

        $this->artisan('app:scrum-198-quitar-items-orden-dia --dry-run')->assertExitCode(0);

        $acta->refresh();
        $this->assertCount(3, $acta->orden_dia);
    }
}
