<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\DocumentType;
use App\Models\TipoPersona;
use App\Models\Departamento;
use App\Models\Ciudad;
use App\Models\SolicitudCredito;
use App\Models\TipoCredito;
use App\Models\Amortizacion;
use App\Models\User;
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

    /**
     * SCRUM-151 (auditoría de datos 2026-07-23): SolicitudCredito.proyecto_departamento_id
     * y proyecto_ciudad_id (Crédito Constructor, "Información del Proyecto") no se
     * reasignaban al fusionar duplicados — el FK onDelete('set null') dejaba esas
     * columnas en NULL silenciosamente, perdiendo la ubicación del proyecto.
     */
    public function test_fusiona_departamento_y_ciudad_duplicados_referenciados_por_solicitud_credito_constructor(): void
    {
        $canonicoDep = Departamento::create(['nombre' => 'Valle']);
        $duplicadoDep = Departamento::create(['nombre' => 'valle']);
        $canonicoCiu = Ciudad::create(['nombre' => 'Cali', 'departamento_id' => $canonicoDep->id]);
        $duplicadoCiu = Ciudad::create(['nombre' => 'cali', 'departamento_id' => $canonicoDep->id]);

        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
        $tipoConstructor = TipoCredito::firstOrCreate(['codigo' => 'CONSTRUCTOR'], ['nombre' => 'Crédito Constructor']);
        $amortizacion = Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);

        $cliente = Cliente::create([
            'tipo_persona_id' => $tipoNatural->id,
            'tipo_documento_id' => $docCC->id,
            'numero_documento' => 'dup_test_2',
            'nombres' => 'Test', 'primer_apellido' => 'Constructor',
            'pais' => 'Colombia', 'departamento_id' => $canonicoDep->id,
        ]);
        $usuario = User::create([
            'name' => 'Registrador Test', 'email' => 'registrador.dup@test.com',
            'password' => bcrypt('password'), 'numero_documento' => 'reg_dup',
            'tipo_documento_id' => $docCC->id, 'roles' => ['coordinador_comercial'],
        ]);

        $solicitud = SolicitudCredito::create([
            'cliente_id' => $cliente->id,
            'usuario_registra_id' => $usuario->id,
            'tipo_credito_id' => $tipoConstructor->id,
            'monto_solicitado' => 100000000,
            'plazo_meses' => 12,
            'amortizacion_id' => $amortizacion->id,
            'destino_recurso' => 'Construcción',
            'fuente_pago' => 'Ventas',
            'correo_notificacion' => 'dup@test.com',
            'asunto_notificacion' => 'Test',
            'mensaje_notificacion' => 'Test',
            'proyecto_departamento_id' => $duplicadoDep->id,
            'proyecto_ciudad_id' => $duplicadoCiu->id,
        ]);

        (new ColombiaGeografiaSeeder())->run();

        $this->assertDatabaseMissing('departamentos', ['id' => $duplicadoDep->id]);
        $this->assertDatabaseMissing('ciudades', ['id' => $duplicadoCiu->id]);
        $this->assertEquals($canonicoDep->id, $solicitud->fresh()->proyecto_departamento_id);
        $this->assertEquals($canonicoCiu->id, $solicitud->fresh()->proyecto_ciudad_id);
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
