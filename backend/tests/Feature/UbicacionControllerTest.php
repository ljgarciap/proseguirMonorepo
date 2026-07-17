<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\Departamento;
use App\Models\Ciudad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UbicacionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);
        $this->user = User::create([
            'name' => 'Operativo Test',
            'email' => 'operativo.ubicacion@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'ubi_test_' . uniqid(),
            'tipo_documento_id' => $docCC->id,
            'roles' => ['operativo'],
        ]);
    }

    public function test_lists_departamentos_ordered_alphabetically(): void
    {
        Departamento::create(['nombre' => 'Valle del Cauca']);
        Departamento::create(['nombre' => 'Antioquia']);

        Passport::actingAs($this->user);
        $response = $this->getJson('/api/ubicaciones/departamentos');

        $response->assertStatus(200);
        $this->assertEquals('Antioquia', $response->json('0.nombre'));
        $this->assertEquals('Valle del Cauca', $response->json('1.nombre'));
    }

    public function test_lists_ciudades_filtered_by_departamento(): void
    {
        $antioquia = Departamento::create(['nombre' => 'Antioquia']);
        $valle = Departamento::create(['nombre' => 'Valle del Cauca']);
        Ciudad::create(['nombre' => 'Medellín', 'departamento_id' => $antioquia->id]);
        Ciudad::create(['nombre' => 'Cali', 'departamento_id' => $valle->id]);

        Passport::actingAs($this->user);
        $response = $this->getJson("/api/ubicaciones/ciudades?departamento_id={$antioquia->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $this->assertEquals('Medellín', $response->json('0.nombre'));
    }

    public function test_ciudades_requires_departamento_id(): void
    {
        Passport::actingAs($this->user);
        $response = $this->getJson('/api/ubicaciones/ciudades');

        $response->assertStatus(422);
    }

    public function test_buscar_ciudades_returns_matches_across_departamentos(): void
    {
        $antioquia = Departamento::create(['nombre' => 'Antioquia']);
        $risaralda = Departamento::create(['nombre' => 'Risaralda']);
        Ciudad::create(['nombre' => 'Medellín', 'departamento_id' => $antioquia->id]);
        Ciudad::create(['nombre' => 'Dosquebradas', 'departamento_id' => $risaralda->id]);

        Passport::actingAs($this->user);
        $response = $this->getJson('/api/ubicaciones/ciudades/buscar?q=Med');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $this->assertEquals('Medellín', $response->json('0.nombre'));
    }

    public function test_buscar_ciudades_requires_at_least_two_characters(): void
    {
        Passport::actingAs($this->user);
        $response = $this->getJson('/api/ubicaciones/ciudades/buscar?q=M');

        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_access_ubicaciones(): void
    {
        $response = $this->getJson('/api/ubicaciones/departamentos');
        $response->assertStatus(401);
    }

    /**
     * SCRUM-118/141: Coordinador Comercial usa los selects de ubicación en
     * Registro Solicitud de Crédito (cliente y proyecto).
     */
    public function test_coordinador_comercial_can_access_ubicaciones(): void
    {
        $coordinador = User::create([
            'name' => 'Coordinador Test',
            'email' => 'coordinador.ubicacion@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'ubi_coord_' . uniqid(),
            'tipo_documento_id' => $this->user->tipo_documento_id,
            'roles' => ['coordinador_comercial'],
        ]);

        Passport::actingAs($coordinador);
        $this->getJson('/api/ubicaciones/departamentos')->assertStatus(200);
    }
}
