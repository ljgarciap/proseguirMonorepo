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

class DocumentAreaTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $operativo;
    private $docCC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);
        $this->admin = $this->makeUser('superadmin', 'sa1');
        $this->operativo = $this->makeUser('operativo', 'op1');
    }

    private function makeUser(string $role, string $suffix): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . $suffix,
            'email' => "{$role}.{$suffix}@test.com",
            'password' => bcrypt('password'),
            'numero_documento' => crc32($role . $suffix),
            'tipo_documento_id' => $this->docCC->id,
            'roles' => [$role],
        ]);
    }

    public function test_superadmin_can_create_area(): void
    {
        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/document-areas', [
            'nombre' => 'Contabilidad', 'codigo' => 'contable',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('codigo', 'contable');
        $response->assertJsonPath('activo', true);
    }

    public function test_non_admin_cannot_create_area(): void
    {
        Passport::actingAs($this->operativo);

        $response = $this->postJson('/api/document-areas', [
            'nombre' => 'Contabilidad', 'codigo' => 'contable',
        ]);

        $response->assertStatus(403);
    }

    public function test_non_admin_can_read_area_index(): void
    {
        DocumentArea::create(['nombre' => 'Contabilidad', 'codigo' => 'contable']);

        Passport::actingAs($this->operativo);
        $response = $this->getJson('/api/document-areas');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_create_rejects_duplicate_codigo(): void
    {
        DocumentArea::create(['nombre' => 'Contabilidad', 'codigo' => 'contable']);
        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/document-areas', [
            'nombre' => 'Contaduría', 'codigo' => 'contable',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['codigo']);
    }

    public function test_superadmin_can_update_area(): void
    {
        $area = DocumentArea::create(['nombre' => 'Contabilidad', 'codigo' => 'contable']);
        Passport::actingAs($this->admin);

        $response = $this->putJson("/api/document-areas/{$area->id}", [
            'nombre' => 'Contabilidad Externa', 'activo' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('nombre', 'Contabilidad Externa');
        $response->assertJsonPath('activo', false);
    }

    public function test_destroy_hard_deletes_area_without_steps(): void
    {
        $area = DocumentArea::create(['nombre' => 'Contabilidad', 'codigo' => 'contable']);
        Passport::actingAs($this->admin);

        $response = $this->deleteJson("/api/document-areas/{$area->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('document_areas', ['id' => $area->id]);
    }

    public function test_destroy_deactivates_area_with_existing_steps_instead_of_deleting(): void
    {
        $area = DocumentArea::create(['nombre' => 'Contabilidad', 'codigo' => 'contable']);
        $categoria = AccountingCategory::create(['nombre' => 'Otros']);
        $prioridad = AccountingPriority::create(['nombre' => 'Baja']);
        $envio = DocumentEnvio::create([
            'sender_id' => $this->operativo->id, 'titulo' => 'Test',
            'categoria_id' => $categoria->id, 'prioridad_id' => $prioridad->id,
            'estado_general' => 'pendiente', 'current_step_order' => 1,
        ]);
        DocumentEnvioStep::create(['envio_id' => $envio->id, 'orden' => 1, 'area_id' => $area->id, 'estado' => 'pendiente']);

        Passport::actingAs($this->admin);
        $response = $this->deleteJson("/api/document-areas/{$area->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('document_areas', ['id' => $area->id, 'activo' => false]);
    }
}
