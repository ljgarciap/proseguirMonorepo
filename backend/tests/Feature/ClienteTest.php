<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Cliente;
use App\Models\TipoPersona;
use App\Models\DocumentType;
use App\Models\Departamento;
use App\Models\Ciudad;
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
    private $departamento;
    private $ciudad;

    protected function setUp(): void
    {
        parent::setUp();

        // Create parameters/dependent tables
        $this->docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);
        $this->docNIT = DocumentType::create(['nombre' => 'Número de Identificación Tributaria', 'codigo' => 'NIT']);

        $this->tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
        $this->tipoJuridica = TipoPersona::firstOrCreate(['codigo' => 'JURIDICA'], ['nombre' => 'Persona Jurídica']);

        $this->departamento = Departamento::create(['nombre' => 'Antioquia']);
        $this->ciudad = Ciudad::create(['nombre' => 'Medellín', 'departamento_id' => $this->departamento->id]);

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
            'departamento_id' => $this->departamento->id,
            'ciudad_id' => $this->ciudad->id,
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

    public function test_store_rejects_ciudad_that_does_not_belong_to_departamento(): void
    {
        Passport::actingAs($this->admin);

        $otroDepartamento = Departamento::create(['nombre' => 'Valle del Cauca']);

        $payload = [
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '1234567-9',
            'nombres' => 'Juan Carlos',
            'primer_apellido' => 'Gomez',
            'pais' => 'Colombia',
            'departamento_id' => $otroDepartamento->id,
            'ciudad_id' => $this->ciudad->id, // pertenece a Antioquia, no a Valle del Cauca
            'activo' => true
        ];

        $response = $this->postJson('/api/clientes', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ciudad_id']);
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
            'departamento_id' => $this->departamento->id,
            'ciudad_id' => $this->ciudad->id,
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

    /**
     * SCRUM-134: búsqueda combinada (?q=) para el autocompletar de cliente —
     * debe encontrar tanto por nombre como por número de documento.
     */
    public function test_index_q_searches_by_nombre_or_numero_documento(): void
    {
        Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '111222333',
            'identificacion' => '111222333',
            'nombre' => 'Roberto Gaviria',
            'nombres' => 'Roberto',
            'primer_apellido' => 'Gaviria',
            'activo' => true
        ]);
        Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '444555666',
            'identificacion' => '444555666',
            'nombre' => 'Marta Londoño',
            'nombres' => 'Marta',
            'primer_apellido' => 'Londoño',
            'activo' => true
        ]);

        Passport::actingAs($this->admin);

        $byNombre = $this->getJson('/api/clientes?q=Gavi');
        $byNombre->assertStatus(200);
        $this->assertCount(1, $byNombre->json());
        $this->assertEquals('Roberto Gaviria', $byNombre->json()[0]['nombre']);

        $byDocumento = $this->getJson('/api/clientes?q=444555');
        $byDocumento->assertStatus(200);
        $this->assertCount(1, $byDocumento->json());
        $this->assertEquals('Marta Londoño', $byDocumento->json()[0]['nombre']);

        $sinCoincidencias = $this->getJson('/api/clientes?q=noexiste');
        $sinCoincidencias->assertStatus(200);
        $this->assertCount(0, $sinCoincidencias->json());
    }

    /**
     * SCRUM-134: alta rápida de cliente Natural con datos mínimos.
     */
    public function test_quick_store_creates_natural_client_with_minimal_data(): void
    {
        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/clientes/quick', [
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '999888777',
            'nombres' => 'Carlos',
            'primer_apellido' => 'Ramirez',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('clientes', [
            'numero_documento' => '999888777',
            'nombres' => 'Carlos',
            'primer_apellido' => 'Ramirez',
            'activo' => true,
            'pais' => null,
            'departamento_id' => null,
        ]);

        // El acceso de usuario se aprovisiona igual que en el alta completa
        $this->assertDatabaseHas('users', [
            'numero_documento' => '999888777',
        ]);
    }

    /**
     * SCRUM-134: alta rápida de cliente Jurídico — no debe exigir representante legal.
     */
    public function test_quick_store_creates_juridica_client_without_representative(): void
    {
        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/clientes/quick', [
            'tipo_persona_id' => $this->tipoJuridica->id,
            'tipo_documento_id' => $this->docNIT->id,
            'numero_documento' => '900111222',
            'nombre_razon_social' => 'Constructora ABC',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('clientes', [
            'numero_documento' => '900111222',
            'nombre_razon_social' => 'Constructora ABC',
            'rep_nombres' => null,
        ]);
    }

    public function test_quick_store_requires_minimal_natural_fields(): void
    {
        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/clientes/quick', [
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '111000111',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nombres', 'primer_apellido']);
    }

    public function test_quick_store_rejects_duplicate_numero_documento(): void
    {
        Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '555000555',
            'identificacion' => '555000555',
            'nombre' => 'Ya Existe',
            'nombres' => 'Ya',
            'primer_apellido' => 'Existe',
            'activo' => true
        ]);

        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/clientes/quick', [
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '555000555',
            'nombres' => 'Otro',
            'primer_apellido' => 'Cliente',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['numero_documento']);
    }

    /**
     * SCRUM-149: Coordinador Comercial tiene acceso completo a la vista de
     * Registro de Clientes (listar, buscar y crear/gestionar), no solo al
     * autocompletar de Registro Solicitud de Crédito (SCRUM-134).
     */
    public function test_coordinador_comercial_can_list_and_manage_clientes(): void
    {
        $coordinador = User::create([
            'name' => 'Coordinador Test',
            'email' => 'coordinador.cliente.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'coord_' . uniqid(),
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['coordinador_comercial']
        ]);

        Passport::actingAs($coordinador);

        $this->getJson('/api/clientes?activo=true')->assertStatus(200);
        $this->getJson('/api/clientes?q=Perez')->assertStatus(200);

        $this->postJson('/api/clientes', [
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '444444',
            'nombres' => 'Si', 'primer_apellido' => 'Autorizado',
            'pais' => 'Colombia', 'departamento_id' => $this->departamento->id,
            'ciudad_id' => $this->ciudad->id, 'activo' => true
        ])->assertStatus(201);
    }
}
