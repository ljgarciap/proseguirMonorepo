<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\DocumentType;
use App\Models\TipoPersona;
use App\Models\Departamento;
use App\Models\Ciudad;
use Database\Seeders\ColombiaGeografiaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColombiaGeografiaSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reproduce el caso real reportado en SCRUM-118 (14/07): dos filas de
     * departamento para "Caldas" con distinta capitalización, como puede
     * pasar en una base con colación case-sensitive donde firstOrCreate()
     * no detecta la coincidencia.
     */
    public function test_fusiona_departamentos_duplicados_por_capitalizacion(): void
    {
        $canonico = Departamento::create(['nombre' => 'Caldas']);
        $duplicado = Departamento::create(['nombre' => 'caldas']);

        Ciudad::create(['nombre' => 'Manizales', 'departamento_id' => $duplicado->id]);

        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
        $cliente = Cliente::create([
            'tipo_persona_id' => $tipoNatural->id,
            'tipo_documento_id' => $docCC->id,
            'numero_documento' => 'dup_test',
            'nombres' => 'Test', 'primer_apellido' => 'Cliente',
            'pais' => 'Colombia', 'departamento_id' => $duplicado->id,
        ]);

        (new ColombiaGeografiaSeeder())->run();

        $this->assertDatabaseMissing('departamentos', ['id' => $duplicado->id]);
        $this->assertDatabaseHas('departamentos', ['id' => $canonico->id, 'nombre' => 'Caldas']);
        $this->assertEquals(1, Departamento::where('nombre', 'Caldas')->count());

        // La ciudad y el cliente que apuntaban al duplicado quedan reasignados.
        $this->assertDatabaseHas('ciudades', ['nombre' => 'Manizales', 'departamento_id' => $canonico->id]);
        $this->assertEquals($canonico->id, $cliente->fresh()->departamento_id);
    }

    public function test_no_duplica_nada_si_se_corre_dos_veces(): void
    {
        (new ColombiaGeografiaSeeder())->run();
        $totalDepartamentos = Departamento::count();
        $totalCiudades = Ciudad::count();

        (new ColombiaGeografiaSeeder())->run();

        $this->assertEquals($totalDepartamentos, Departamento::count());
        $this->assertEquals($totalCiudades, Ciudad::count());
        $this->assertEquals(33, Departamento::count());
    }

    public function test_carga_el_catalogo_sin_duplicados_previos(): void
    {
        (new ColombiaGeografiaSeeder())->run();

        $this->assertDatabaseHas('departamentos', ['nombre' => 'Antioquia']);
        $this->assertGreaterThan(1000, Ciudad::count());
    }
}
