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

    private function makeDoc(array $overrides = []): array
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        return array_merge([
            'titulo'       => 'Test Doc',
            'archivo'      => $file,
            'target_role'  => 'gerente',
            'categoria_id' => $this->categoria->id,
            'prioridad_id' => $this->prioridad->id,
        ], $overrides);
    }

    private function makeUser(string $role, string $suffix): \App\Models\User
    {
        return \App\Models\User::create([
            'name'               => ucfirst($role) . ' ' . $suffix,
            'email'              => "{$role}.{$suffix}@test.com",
            'password'           => bcrypt('password'),
            'numero_documento'   => crc32($role . $suffix),
            'tipo_documento_id'  => $this->docCC->id,
            'roles'              => [$role],
        ]);
    }

    public function test_superadmin_sees_all_documents(): void
    {
        $superadmin = $this->makeUser('superadmin', 'sa1');

        Passport::actingAs($this->coordinador);
        $this->postJson('/api/internal-docs', $this->makeDoc(['target_role' => 'gerente']))->assertStatus(201);
        $this->postJson('/api/internal-docs', $this->makeDoc(['target_role' => 'operativo']))->assertStatus(201);

        Passport::actingAs($superadmin);
        $response = $this->getJson('/api/internal-docs')->assertStatus(200);
        $this->assertCount(2, $response->json());
    }

    public function test_contable_sees_only_contable_targeted_docs(): void
    {
        $contable = $this->makeUser('contable', 'c1');

        Passport::actingAs($this->operativo);
        $this->postJson('/api/internal-docs', $this->makeDoc(['target_role' => 'contable']))->assertStatus(201);
        $this->postJson('/api/internal-docs', $this->makeDoc(['target_role' => 'gerente']))->assertStatus(201);

        Passport::actingAs($contable);
        $response = $this->getJson('/api/internal-docs')->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals('contable', $response->json('0.target_role'));
    }

    public function test_operativo_sees_directly_targeted_docs(): void
    {
        Passport::actingAs($this->gerente);
        $this->postJson('/api/internal-docs', $this->makeDoc(['target_role' => 'operativo']))->assertStatus(201);

        Passport::actingAs($this->operativo);
        $response = $this->getJson('/api/internal-docs')->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals('operativo', $response->json('0.target_role'));
    }

    public function test_sender_sees_own_documents(): void
    {
        Passport::actingAs($this->coordinador);
        $this->postJson('/api/internal-docs', $this->makeDoc(['target_role' => 'gerente']))->assertStatus(201);

        // Coordinador sees their own doc via sender_id (pending so gerente can't see it yet)
        $response = $this->getJson('/api/internal-docs')->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals($this->coordinador->id, $response->json('0.sender_id'));
    }

    public function test_gerente_sees_non_coordinator_docs_without_escalation(): void
    {
        // Operativo (not coordinador_comercial) sends to gerente
        Passport::actingAs($this->operativo);
        $this->postJson('/api/internal-docs', $this->makeDoc(['target_role' => 'gerente']))->assertStatus(201);

        // Gerente SHOULD see it immediately — no escalation needed for non-coordinator senders
        Passport::actingAs($this->gerente);
        $response = $this->getJson('/api/internal-docs')->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals('gerente', $response->json('0.target_role'));
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
