<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\InternalDocument;
use App\Models\AccountingCategory;
use App\Models\AccountingPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class InternalDocumentEscalationTest extends TestCase
{
    use RefreshDatabase;

    private $coordinador;
    private $operativo;
    private $gerente;
    private $categoria;
    private $prioridad;
    private $docCC;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);

        $this->coordinador = User::create([
            'name' => 'Coordinador Test',
            'email' => 'coordinador.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '111111',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['coordinador_comercial']
        ]);

        $this->operativo = User::create([
            'name' => 'Operativo Test',
            'email' => 'operativo.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '222222',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['operativo']
        ]);

        $this->gerente = User::create([
            'name' => 'Gerente Test',
            'email' => 'gerente.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '333333',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['gerente']
        ]);

        $this->categoria = AccountingCategory::create(['nombre' => 'Extractos Bancarios', 'codigo' => 'EB']);
        $this->prioridad = AccountingPriority::create(['nombre' => 'Baja', 'horas_vencimiento' => 24, 'color' => '#ffffff']);
    }

    public function test_three_step_escalated_approval_workflow(): void
    {
        // 1. Coordinator uploads document targeting Manager
        Passport::actingAs($this->coordinador);
        $file = UploadedFile::fake()->create('reporte.pdf', 500, 'application/pdf');

        $response = $this->postJson('/api/internal-docs', [
            'titulo' => 'Reporte Mensual',
            'archivo' => $file,
            'target_role' => 'gerente',
            'categoria_id' => $this->categoria->id,
            'prioridad_id' => $this->prioridad->id
        ]);

        $response->assertStatus(201);
        $docId = $response->json('id');

        // 2. Manager should NOT see this pending document in index yet
        Passport::actingAs($this->gerente);
        $response = $this->getJson('/api/internal-docs');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json());

        // 3. Operator SHOULD see this pending document in index
        Passport::actingAs($this->operativo);
        $response = $this->getJson('/api/internal-docs');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals($docId, $response->json('0.id'));

        // 4. Operator gives visto_bueno (seen review)
        $response = $this->patchJson("/api/internal-docs/{$docId}/status", [
            'estado' => 'visto_bueno'
        ]);
        $response->assertStatus(200);
        $this->assertEquals('visto_bueno', $response->json('estado'));
        $this->assertNotNull($response->json('last_action_at'));
        $this->assertNotNull($response->json('last_modified'));

        // 5. Manager SHOULD now see the document in index
        Passport::actingAs($this->gerente);
        $response = $this->getJson('/api/internal-docs');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals($docId, $response->json('0.id'));
        $this->assertNotNull($response->json('0.last_action_at'));

        // 6. Manager approves and processes it finally
        $response = $this->patchJson("/api/internal-docs/{$docId}/status", [
            'estado' => 'procesado'
        ]);
        $response->assertStatus(200);
        $this->assertEquals('procesado', $response->json('estado'));
    }
}
