<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Cliente;
use App\Models\TipoPersona;
use App\Models\DocumentType;
use App\Models\TipoCredito;
use App\Models\Amortizacion;
use App\Models\Visita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class VisitaTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $tipoNatural;
    private $docCC;
    
    // seeded params
    private $creditoOrdinario;
    private $amortizacionMensual;

    // active client
    private $activeClient;
    private $inactiveClient;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create document types and person types
        $this->docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $this->tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);

        // 2. Create parametric values
        $this->creditoOrdinario = TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
        $this->amortizacionMensual = Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);

        // 3. Create active and inactive clients
        $this->activeClient = Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '11111',
            'identificacion' => '11111',
            'nombre' => 'Active Client',
            'nombres' => 'Active',
            'primer_apellido' => 'Client',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true
        ]);

        $this->inactiveClient = Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '22222',
            'identificacion' => '22222',
            'nombre' => 'Inactive Client',
            'nombres' => 'Inactive',
            'primer_apellido' => 'Client',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => false
        ]);

        // 4. Admin setup
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
     * Test list visits ordered properly.
     */
    public function test_admin_can_list_visits_ordered_properly(): void
    {
        Visita::create([
            'fecha' => '2026-06-01',
            'ciudad' => 'Medellin',
            'cliente_id' => $this->activeClient->id,
            'asistentes' => 'Juan, Pedro',
            'requiere_credito' => false
        ]);

        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/visitas');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals('Medellin', $response->json()[0]['ciudad']);
    }

    /**
     * Test store requires credit fields when requiere_credito is true.
     */
    public function test_store_visitas_validates_credit_fields_when_required(): void
    {
        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/visitas', [
            'fecha' => '2026-06-01',
            'ciudad' => 'Manizales',
            'cliente_id' => $this->activeClient->id,
            'asistentes' => 'Carlos, Luis',
            'requiere_credito' => true // demands credit fields
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'tipo_credito_id',
            'monto_solicitado',
            'plazo',
            'amortizacion_id',
            'destino_recurso',
            'fuente_pago'
        ]);
    }

    /**
     * Test store visit for inactive client fails.
     */
    public function test_store_visit_fails_if_client_is_inactive(): void
    {
        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/visitas', [
            'fecha' => '2026-06-01',
            'ciudad' => 'Pereira',
            'cliente_id' => $this->inactiveClient->id,
            'asistentes' => 'Carlos, Luis',
            'requiere_credito' => false
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('activos', $response->json()['message']);
    }

    /**
     * Test store visit successfully.
     */
    public function test_store_visit_successfully_with_credit(): void
    {
        Passport::actingAs($this->admin);

        $payload = [
            'fecha' => '2026-06-02',
            'ciudad' => 'Pereira',
            'cliente_id' => $this->activeClient->id,
            'asistentes' => 'Carlos, Luis',
            'requiere_credito' => true,
            'tipo_credito_id' => $this->creditoOrdinario->id,
            'monto_solicitado' => 50000000.00,
            'plazo' => 12,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Capital de trabajo para inventarios',
            'garantia' => 'Firma personal',
            'fuente_pago' => 'Flujo de caja operacional'
        ];

        $response = $this->postJson('/api/visitas', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('visitas', [
            'ciudad' => 'Pereira',
            'requiere_credito' => true,
            'monto_solicitado' => 50000000.00,
            'plazo' => 12
        ]);
    }

    /**
     * Test update visit.
     */
    public function test_update_visit_successfully(): void
    {
        $visit = Visita::create([
            'fecha' => '2026-06-01',
            'ciudad' => 'Cali',
            'cliente_id' => $this->activeClient->id,
            'asistentes' => 'Antigravity',
            'requiere_credito' => false
        ]);

        Passport::actingAs($this->admin);

        $response = $this->putJson("/api/visitas/{$visit->id}", [
            'fecha' => '2026-06-01',
            'ciudad' => 'Cali Modificado',
            'cliente_id' => $this->activeClient->id,
            'asistentes' => 'Antigravity, User',
            'requiere_credito' => false
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Cali Modificado', $response->json()['ciudad']);
    }

    /**
     * Test delete visit.
     */
    public function test_delete_visit(): void
    {
        $visit = Visita::create([
            'fecha' => '2026-06-01',
            'ciudad' => 'Armenia',
            'cliente_id' => $this->activeClient->id,
            'asistentes' => 'Antigravity',
            'requiere_credito' => false
        ]);

        Passport::actingAs($this->admin);

        $response = $this->deleteJson("/api/visitas/{$visit->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('visitas', [
            'id' => $visit->id
        ]);
    }
}
