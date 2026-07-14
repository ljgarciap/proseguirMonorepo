<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\DocumentType;
use App\Models\TipoPersona;
use App\Models\Departamento;
use App\Models\Ciudad;
use App\Models\Visita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchVisitasUbicacionTest extends TestCase
{
    use RefreshDatabase;

    private $antioquia;
    private $boyaca;
    private $tolima;
    private $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->antioquia = Departamento::create(['nombre' => 'Antioquia']);
        $this->boyaca = Departamento::create(['nombre' => 'Boyacá']);
        $this->tolima = Departamento::create(['nombre' => 'Tolima']);

        Ciudad::create(['nombre' => 'Medellín', 'departamento_id' => $this->antioquia->id]);
        Ciudad::create(['nombre' => 'Ibagué', 'departamento_id' => $this->tolima->id]);
        // "Caldas" existe como ciudad en dos departamentos -> caso ambiguo real
        Ciudad::create(['nombre' => 'Caldas', 'departamento_id' => $this->antioquia->id]);
        Ciudad::create(['nombre' => 'Caldas', 'departamento_id' => $this->boyaca->id]);

        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
        $this->cliente = Cliente::create([
            'tipo_persona_id' => $tipoNatural->id,
            'tipo_documento_id' => $docCC->id,
            'numero_documento' => 'match_visitas_test',
            'nombres' => 'Test', 'primer_apellido' => 'Cliente',
            'pais' => 'Colombia', 'activo' => true,
        ]);
    }

    private function makeVisita(?string $ciudad): Visita
    {
        return Visita::create([
            'fecha' => '2026-06-01',
            'ciudad' => $ciudad,
            'cliente_id' => $this->cliente->id,
            'asistentes' => 'Test',
            'requiere_credito' => false,
        ]);
    }

    public function test_dry_run_does_not_write_anything(): void
    {
        $visita = $this->makeVisita('MEDELLIN');

        $this->artisan('app:match-visitas-ubicacion --dry-run')->assertExitCode(0);

        $this->assertNull($visita->fresh()->ciudad_id);
    }

    public function test_matches_unambiguous_city_without_accents(): void
    {
        $visita = $this->makeVisita('MEDELLIN');

        $this->artisan('app:match-visitas-ubicacion')->assertExitCode(0);

        $visita->refresh();
        $this->assertEquals($this->antioquia->id, $visita->departamento_id);
        $this->assertEquals(Ciudad::where('nombre', 'Medellín')->first()->id, $visita->ciudad_id);
    }

    public function test_ambiguous_city_across_multiple_departments_is_not_matched(): void
    {
        $visita = $this->makeVisita('Caldas');

        $this->artisan('app:match-visitas-ubicacion')->assertExitCode(0);

        $visita->refresh();
        $this->assertNull($visita->departamento_id);
        $this->assertNull($visita->ciudad_id);
        $this->assertEquals('Caldas', $visita->ciudad);
    }

    public function test_unknown_city_is_not_matched(): void
    {
        $visita = $this->makeVisita('Ciudad Inexistente');

        $this->artisan('app:match-visitas-ubicacion')->assertExitCode(0);

        $visita->refresh();
        $this->assertNull($visita->departamento_id);
        $this->assertNull($visita->ciudad_id);
    }

    public function test_already_matched_visitas_are_not_reprocessed(): void
    {
        $visita = $this->makeVisita('IBAGUE');

        $this->artisan('app:match-visitas-ubicacion')->assertExitCode(0);
        $visita->refresh();
        $primerMatch = $visita->ciudad_id;

        $this->artisan('app:match-visitas-ubicacion')->assertExitCode(0);

        $this->assertEquals($primerMatch, $visita->fresh()->ciudad_id);
    }
}
