<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\DocumentArea;
use App\Models\DocumentEnvio;
use App\Models\DocumentEnvioStep;
use App\Models\AccountingCategory;
use App\Models\AccountingPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DocumentEnvioVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private $sender;
    private $contabilidad;
    private $gerencia;
    private $categoria;
    private $prioridad;

    protected function setUp(): void
    {
        parent::setUp();

        $docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);

        $this->sender = $this->makeUser($docCC, 'operativo', 'sender');
        $this->categoria = AccountingCategory::create(['nombre' => 'Extractos Bancarios']);
        $this->prioridad = AccountingPriority::create(['nombre' => 'Baja']);
        $this->contabilidad = DocumentArea::create(['nombre' => 'Contabilidad', 'codigo' => 'contable']);
        $this->gerencia = DocumentArea::create(['nombre' => 'Gerencia', 'codigo' => 'gerente']);

        $this->docCC = $docCC;
    }

    private function makeUser(DocumentType $docCC, string $role, string $suffix): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . $suffix,
            'email' => "{$role}.{$suffix}@test.com",
            'password' => bcrypt('password'),
            'numero_documento' => crc32($role . $suffix),
            'tipo_documento_id' => $docCC->id,
            'roles' => [$role],
        ]);
    }

    private function makeEnvio(): DocumentEnvio
    {
        $envio = DocumentEnvio::create([
            'sender_id' => $this->sender->id,
            'titulo' => 'Reporte Mensual',
            'categoria_id' => $this->categoria->id,
            'prioridad_id' => $this->prioridad->id,
            'estado_general' => 'pendiente',
            'current_step_order' => 1,
        ]);

        DocumentEnvioStep::create([
            'envio_id' => $envio->id, 'orden' => 1, 'area_id' => $this->contabilidad->id, 'estado' => 'pendiente',
        ]);
        DocumentEnvioStep::create([
            'envio_id' => $envio->id, 'orden' => 2, 'area_id' => $this->gerencia->id, 'estado' => 'pendiente',
        ]);

        return $envio;
    }

    public function test_superadmin_sees_all_envios(): void
    {
        $this->makeEnvio();
        $admin = $this->makeUser($this->docCC, 'superadmin', 'sa1');

        Passport::actingAs($admin);
        $response = $this->getJson('/api/document-envios')->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    public function test_sender_sees_own_envio_regardless_of_step(): void
    {
        $this->makeEnvio();

        Passport::actingAs($this->sender);
        $response = $this->getJson('/api/document-envios')->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    public function test_current_step_area_sees_pending_envio(): void
    {
        $this->makeEnvio();
        $contable = $this->makeUser($this->docCC, 'contable', 'c1');

        Passport::actingAs($contable);
        $response = $this->getJson('/api/document-envios')->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    public function test_future_step_area_does_not_see_envio_yet(): void
    {
        $this->makeEnvio();
        $gerente = $this->makeUser($this->docCC, 'gerente', 'g1');

        Passport::actingAs($gerente);
        $response = $this->getJson('/api/document-envios')->assertStatus(200);
        $this->assertCount(0, $response->json());
    }

    public function test_area_outside_route_does_not_see_envio(): void
    {
        $this->makeEnvio();
        $coordinador = $this->makeUser($this->docCC, 'coordinador_comercial', 'cc1');

        Passport::actingAs($coordinador);
        $response = $this->getJson('/api/document-envios')->assertStatus(200);
        $this->assertCount(0, $response->json());
    }

    public function test_area_that_already_processed_still_sees_envio_in_history(): void
    {
        $envio = $this->makeEnvio();
        $envio->steps()->where('orden', 1)->first()->update(['estado' => 'procesado']);
        $envio->update(['current_step_order' => 2]);

        $contable = $this->makeUser($this->docCC, 'contable', 'c3');

        Passport::actingAs($contable);
        $response = $this->getJson('/api/document-envios')->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    public function test_next_step_area_sees_envio_once_its_turn_comes(): void
    {
        $envio = $this->makeEnvio();
        $envio->steps()->where('orden', 1)->first()->update(['estado' => 'procesado']);
        $envio->update(['current_step_order' => 2]);

        $gerente = $this->makeUser($this->docCC, 'gerente', 'g2');

        Passport::actingAs($gerente);
        $response = $this->getJson('/api/document-envios')->assertStatus(200);
        $this->assertCount(1, $response->json());
    }
}
