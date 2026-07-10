<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\DocumentArea;
use App\Models\AccountingCategory;
use App\Models\AccountingPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DocumentEnvioTest extends TestCase
{
    use RefreshDatabase;

    private $sender;
    private $categoria;
    private $prioridad;
    private $contabilidad;
    private $gerencia;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);

        $this->sender = User::create([
            'name' => 'Operativo Test',
            'email' => 'operativo.envio@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '444444',
            'tipo_documento_id' => $docCC->id,
            'roles' => ['operativo'],
        ]);

        $this->categoria = AccountingCategory::create(['nombre' => 'Extractos Bancarios']);
        $this->prioridad = AccountingPriority::create(['nombre' => 'Baja']);

        $this->contabilidad = DocumentArea::create(['nombre' => 'Contabilidad', 'codigo' => 'contabilidad']);
        $this->gerencia = DocumentArea::create(['nombre' => 'Gerencia', 'codigo' => 'gerente']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'titulo' => 'Reporte Mensual',
            'ruta' => [$this->contabilidad->id, $this->gerencia->id],
            'categoria_id' => $this->categoria->id,
            'prioridad_id' => $this->prioridad->id,
            'archivos' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        ], $overrides);
    }

    public function test_creates_envio_with_ordered_steps_and_files(): void
    {
        Passport::actingAs($this->sender);

        $response = $this->postJson('/api/document-envios', $this->payload());

        $response->assertStatus(201);
        $response->assertJsonPath('estado_general', 'pendiente');
        $response->assertJsonPath('current_step_order', 1);
        $response->assertJsonCount(2, 'steps');
        $response->assertJsonPath('steps.0.orden', 1);
        $response->assertJsonPath('steps.0.area.codigo', 'contabilidad');
        $response->assertJsonPath('steps.1.orden', 2);
        $response->assertJsonPath('steps.1.area.codigo', 'gerente');
        $response->assertJsonCount(1, 'files');
    }

    public function test_accepts_multiple_files(): void
    {
        Passport::actingAs($this->sender);

        $response = $this->postJson('/api/document-envios', $this->payload([
            'archivos' => [
                UploadedFile::fake()->create('doc1.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('doc2.pdf', 100, 'application/pdf'),
            ],
        ]));

        $response->assertStatus(201);
        $response->assertJsonCount(2, 'files');
    }

    public function test_rejects_duplicate_areas_in_ruta(): void
    {
        Passport::actingAs($this->sender);

        $response = $this->postJson('/api/document-envios', $this->payload([
            'ruta' => [$this->contabilidad->id, $this->contabilidad->id],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ruta.0', 'ruta.1']);
    }

    public function test_rejects_empty_ruta(): void
    {
        Passport::actingAs($this->sender);

        $response = $this->postJson('/api/document-envios', $this->payload(['ruta' => []]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ruta']);
    }

    public function test_rejects_missing_files(): void
    {
        Passport::actingAs($this->sender);

        $data = $this->payload();
        unset($data['archivos']);

        $response = $this->postJson('/api/document-envios', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['archivos']);
    }

    public function test_rejects_missing_titulo(): void
    {
        Passport::actingAs($this->sender);

        $data = $this->payload();
        unset($data['titulo']);

        $response = $this->postJson('/api/document-envios', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['titulo']);
    }
}
