<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\DocumentType;
use App\Models\TipoPersona;
use App\Models\Departamento;
use App\Models\Ciudad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchClientesUbicacionTest extends TestCase
{
    use RefreshDatabase;

    private $docCC;
    private $tipoNatural;
    private $antioquia;
    private $boyaca;
    private $tolima;
    private $risaralda;
    private $bogota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);
        $this->tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);

        $this->antioquia = Departamento::create(['nombre' => 'Antioquia']);
        $this->boyaca = Departamento::create(['nombre' => 'Boyacá']);
        $this->tolima = Departamento::create(['nombre' => 'Tolima']);
        $this->risaralda = Departamento::create(['nombre' => 'Risaralda']);
        $this->bogota = Departamento::create(['nombre' => 'Bogotá D.C.']);

        Ciudad::create(['nombre' => 'Medellín', 'departamento_id' => $this->antioquia->id]);
        // "Caldas" existe como ciudad en dos departamentos distintos -> caso ambiguo real
        Ciudad::create(['nombre' => 'Caldas', 'departamento_id' => $this->antioquia->id]);
        Ciudad::create(['nombre' => 'Caldas', 'departamento_id' => $this->boyaca->id]);
        Ciudad::create(['nombre' => 'Ibagué', 'departamento_id' => $this->tolima->id]);
        Ciudad::create(['nombre' => 'Dosquebradas', 'departamento_id' => $this->risaralda->id]);
        Ciudad::create(['nombre' => 'Bogotá D.C.', 'departamento_id' => $this->bogota->id]);
    }

    private function makeCliente(?string $departamento, ?string $ciudad): Cliente
    {
        return Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => 'doc_' . uniqid(),
            'nombres' => 'Test',
            'primer_apellido' => 'Cliente',
            'pais' => 'Colombia',
            'departamento' => $departamento,
            'ciudad' => $ciudad,
            'activo' => true,
        ]);
    }

    public function test_dry_run_does_not_write_anything(): void
    {
        $cliente = $this->makeCliente(null, 'MEDELLIN');

        $this->artisan('app:match-clientes-ubicacion --dry-run')->assertExitCode(0);

        $this->assertNull($cliente->fresh()->departamento_id);
        $this->assertNull($cliente->fresh()->ciudad_id);
    }

    public function test_matches_uppercase_city_without_accents(): void
    {
        $cliente = $this->makeCliente(null, 'MEDELLIN');

        $this->artisan('app:match-clientes-ubicacion')->assertExitCode(0);

        $cliente->refresh();
        $this->assertEquals($this->antioquia->id, $cliente->departamento_id);
    }

    public function test_matches_bogota_without_dc_suffix(): void
    {
        $cliente = $this->makeCliente(null, 'BOGOTA');

        $this->artisan('app:match-clientes-ubicacion')->assertExitCode(0);

        $cliente->refresh();
        $this->assertEquals($this->bogota->id, $cliente->departamento_id);
    }

    public function test_matches_city_name_without_spaces(): void
    {
        $cliente = $this->makeCliente(null, 'DOS QUEBRADAS');

        $this->artisan('app:match-clientes-ubicacion')->assertExitCode(0);

        $cliente->refresh();
        $this->assertEquals($this->risaralda->id, $cliente->departamento_id);
    }

    public function test_splits_combined_city_and_department_string(): void
    {
        $cliente = $this->makeCliente(null, 'Ibagué - Tolima');

        $this->artisan('app:match-clientes-ubicacion')->assertExitCode(0);

        $cliente->refresh();
        $this->assertEquals($this->tolima->id, $cliente->departamento_id);
    }

    public function test_ambiguous_city_across_multiple_departments_is_not_matched(): void
    {
        $cliente = $this->makeCliente(null, 'Caldas');

        $this->artisan('app:match-clientes-ubicacion')->assertExitCode(0);

        $cliente->refresh();
        $this->assertNull($cliente->departamento_id);
        $this->assertNull($cliente->ciudad_id);
        // El texto original se conserva para revisión manual.
        $this->assertEquals('Caldas', $cliente->ciudad);
    }

    public function test_inconsistent_departamento_and_ciudad_is_not_matched(): void
    {
        // La ciudad "Medellín" no pertenece al departamento "Boyacá".
        $cliente = $this->makeCliente('Boyacá', 'Medellín');

        $this->artisan('app:match-clientes-ubicacion')->assertExitCode(0);

        $cliente->refresh();
        $this->assertNull($cliente->departamento_id);
        $this->assertNull($cliente->ciudad_id);
    }

    public function test_already_matched_clients_are_not_reprocessed(): void
    {
        $cliente = $this->makeCliente(null, 'MEDELLIN');
        $cliente->update(['departamento_id' => $this->antioquia->id, 'ciudad_id' => Ciudad::where('nombre', 'Medellín')->first()->id]);

        $otro = $this->makeCliente(null, 'IBAGUE');

        $this->artisan('app:match-clientes-ubicacion')->assertExitCode(0);

        $otro->refresh();
        $this->assertEquals($this->tolima->id, $otro->departamento_id);
    }
}
