<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\DocumentArea;
use App\Models\DocumentEnvio;
use App\Models\DocumentEnvioStep;
use App\Models\DocumentEnvioFile;
use App\Models\AccountingCategory;
use App\Models\AccountingPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DocumentEnvioDestroyTest extends TestCase
{
    use RefreshDatabase;

    private $sender;
    private $docCC;
    private $categoria;
    private $prioridad;
    private $area;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);
        $this->sender = $this->makeUser('operativo', 'sender');
        $this->categoria = AccountingCategory::create(['nombre' => 'Extractos Bancarios']);
        $this->prioridad = AccountingPriority::create(['nombre' => 'Baja']);
        $this->area = DocumentArea::create(['nombre' => 'Contabilidad', 'codigo' => 'contable']);
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

    private function makeEnvio(string $estadoGeneral = 'pendiente'): DocumentEnvio
    {
        $envio = DocumentEnvio::create([
            'sender_id' => $this->sender->id,
            'titulo' => 'Envio Test',
            'categoria_id' => $this->categoria->id,
            'prioridad_id' => $this->prioridad->id,
            'estado_general' => $estadoGeneral,
            'current_step_order' => 1,
        ]);
        DocumentEnvioStep::create(['envio_id' => $envio->id, 'orden' => 1, 'area_id' => $this->area->id, 'estado' => 'pendiente']);

        Storage::disk('public')->put('internal_docs/archivo.pdf', 'contenido');
        DocumentEnvioFile::create(['envio_id' => $envio->id, 'path' => 'internal_docs/archivo.pdf', 'original_name' => 'archivo.pdf']);

        return $envio;
    }

    public function test_sender_can_delete_own_pending_envio(): void
    {
        $envio = $this->makeEnvio('pendiente');

        Passport::actingAs($this->sender);
        $response = $this->deleteJson("/api/document-envios/{$envio->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('document_envios', ['id' => $envio->id]);
        $this->assertDatabaseMissing('document_envio_steps', ['envio_id' => $envio->id]);
        $this->assertDatabaseMissing('document_envio_files', ['envio_id' => $envio->id]);
        Storage::disk('public')->assertMissing('internal_docs/archivo.pdf');
    }

    public function test_sender_cannot_delete_envio_already_in_progress(): void
    {
        $envio = $this->makeEnvio('en_proceso');

        Passport::actingAs($this->sender);
        $response = $this->deleteJson("/api/document-envios/{$envio->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('document_envios', ['id' => $envio->id]);
    }

    public function test_other_user_cannot_delete_envio(): void
    {
        $envio = $this->makeEnvio('pendiente');
        $otro = $this->makeUser('contable', 'otro');

        Passport::actingAs($otro);
        $response = $this->deleteJson("/api/document-envios/{$envio->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('document_envios', ['id' => $envio->id]);
    }

    public function test_superadmin_can_delete_regardless_of_estado(): void
    {
        $envio = $this->makeEnvio('en_proceso');
        $admin = $this->makeUser('superadmin', 'admin');

        Passport::actingAs($admin);
        $response = $this->deleteJson("/api/document-envios/{$envio->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('document_envios', ['id' => $envio->id]);
    }
}
