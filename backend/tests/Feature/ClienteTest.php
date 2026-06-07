<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Cliente;
use App\Models\TipoPersona;
use App\Models\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ClienteTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $tipoNatural;
    private $tipoJuridica;
    private $docCC;
    private $docNIT;

    protected function setUp(): void
    {
        parent::setUp();

        // Create parameters/dependent tables
        $this->docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);
        $this->docNIT = DocumentType::create(['nombre' => 'Número de Identificación Tributaria', 'codigo' => 'NIT']);

        $this->tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
        $this->tipoJuridica = TipoPersona::firstOrCreate(['codigo' => 'JURIDICA'], ['nombre' => 'Persona Jurídica']);

        // Create superadmin user for authentication
        $this->admin = User::create([
            'name' => 'Super Administrador Test',
            'email' => 'admin.test.' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'admin_doc_' . uniqid(),
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['superadmin']
        ]);
    }

    /**
     * Test fetching clients ordered alphabetically by Name and Type.
     */
    public function test_admin_can_list_clients_ordered_alphabetically(): void
    {
        Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '10001',
            'identificacion' => '10001',
            'nombre' => 'Zacarias Lopez',
            'nombres' => 'Zacarias',
            'primer_apellido' => 'Lopez',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true
        ]);

        Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '10002',
            'identificacion' => '10002',
            'nombre' => 'Ana Gomez',
            'nombres' => 'Ana',
            'primer_apellido' => 'Gomez',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true
        ]);

        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/clientes');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals('Ana Gomez', $data[0]['nombre']);
        $this->assertEquals('Zacarias Lopez', $data[count($data) - 1]['nombre']);
    }

    /**
     * Test filtering clients.
     */
    public function test_can_filter_clients(): void
    {
        Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '55555',
            'identificacion' => '55555',
            'nombre' => 'Client Filter 1',
            'nombres' => 'Client',
            'primer_apellido' => 'Filter 1',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true
        ]);

        Cliente::create([
            'tipo_persona_id' => $this->tipoJuridica->id,
            'tipo_documento_id' => $this->docNIT->id,
            'numero_documento' => '99999',
            'identificacion' => '99999',
            'nombre' => 'Company Filter 2',
            'nombre_razon_social' => 'Company Filter 2',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => false
        ]);

        Passport::actingAs($this->admin);

        // Filter by status (active)
        $response = $this->getJson('/api/clientes?activo=true');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals('55555', $response->json()[0]['numero_documento']);

        // Filter by type
        $response = $this->getJson('/api/clientes?tipo_persona=Jur');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals('99999', $response->json()[0]['numero_documento']);
    }

    /**
     * Test creation of natural person and automatic user account creation.
     */
    public function test_store_natural_person_creates_client_and_user_access(): void
    {
        Passport::actingAs($this->admin);

        $payload = [
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '1234567-8',
            'nombres' => 'Juan Carlos',
            'primer_apellido' => 'Gomez',
            'segundo_apellido' => 'Perez',
            'correo_electronico' => 'juan@test.com',
            'telefono' => '3001234567',
            'pais' => 'Colombia',
            'departamento' => 'Antioquia',
            'ciudad' => 'Medellin',
            'direccion' => 'Calle 10 # 5-6',
            'ocupacion' => 'Ingeniero',
            'activo' => true
        ];

        $response = $this->postJson('/api/clientes', $payload);

        $response->assertStatus(201);
        
        // Assert client exists in DB
        $this->assertDatabaseHas('clientes', [
            'numero_documento' => '1234567-8',
            'nombre' => 'Juan Carlos Gomez Perez',
            'activo' => true
        ]);

        // Assert User account was automatically created
        $this->assertDatabaseHas('users', [
            'numero_documento' => '1234567-8',
            'email' => 'juan@test.com'
        ]);

        $user = User::where('numero_documento', '1234567-8')->first();
        $this->assertNotNull($user);
        $this->assertContains('cliente', $user->roles);

        // Assert default password is the document number without hyphens
        $this->assertTrue(Hash::check('12345678', $user->password));
    }

    /**
     * Test creation of juridical person and legal representative.
     */
    public function test_store_juridical_person_creates_client_and_user_access(): void
    {
        Passport::actingAs($this->admin);

        $payload = [
            'tipo_persona_id' => $this->tipoJuridica->id,
            'tipo_documento_id' => $this->docNIT->id,
            'numero_documento' => '900.123.456-7',
            'nombre_razon_social' => 'Inversiones ABC SAS',
            'tipo_empresa' => 'SAS',
            'actividad_economica' => 'Comercio',
            'correo_electronico_empresarial' => 'contacto@abc.com',
            'pais' => 'Colombia',
            'departamento' => 'Bogota',
            'ciudad' => 'Bogota',
            'activo' => true,

            // Legal representative info
            'rep_tipo_documento_id' => $this->docCC->id,
            'rep_numero_documento' => '888888',
            'rep_nombres' => 'Carlos Rep',
            'rep_primer_apellido' => 'Legal',
            'rep_cargo' => 'Gerente General',
            'rep_correo_electronico' => 'carlos@abc.com'
        ];

        $response = $this->postJson('/api/clientes', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('clientes', [
            'numero_documento' => '900.123.456-7',
            'nombre' => 'Inversiones ABC SAS',
            'rep_nombres' => 'Carlos Rep'
        ]);

        // Assert user password removes hyphens (900.123.4567)
        $user = User::where('numero_documento', '900.123.456-7')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('900.123.4567', $user->password));
    }

    /**
     * Test deactivating client soft deletes corresponding user.
     */
    public function test_deactivating_client_soft_deletes_linked_user(): void
    {
        // Create client first
        $client = Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '77777',
            'identificacion' => '77777',
            'nombre' => 'Jane Doe',
            'nombres' => 'Jane',
            'primer_apellido' => 'Doe',
            'correo_electronico' => 'jane@doe.com',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true
        ]);

        // Mock User manually (representing synced account)
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@doe.com',
            'numero_documento' => '77777',
            'password' => bcrypt('77777'),
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['cliente']
        ]);

        Passport::actingAs($this->admin);

        // Deactivate client (DELETE request)
        $response = $this->deleteJson("/api/clientes/{$client->id}");
        $response->assertStatus(200);

        // Verify client active is false
        $this->assertFalse((bool) $client->fresh()->activo);

        // Verify user is soft-deleted
        $this->assertTrue($user->fresh()->trashed());
    }
}
