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

class DocumentEnvioDownloadTest extends TestCase
{
    use RefreshDatabase;

    private $sender;
    private $contabilidad;
    private $gerencia;
    private $envio;
    private $file;
    private $docCC;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);
        $this->sender = $this->makeUser('operativo', 'sender');
        $categoria = AccountingCategory::create(['nombre' => 'Extractos Bancarios']);
        $prioridad = AccountingPriority::create(['nombre' => 'Baja']);
        $this->contabilidad = DocumentArea::create(['nombre' => 'Contabilidad', 'codigo' => 'contable']);
        $this->gerencia = DocumentArea::create(['nombre' => 'Gerencia', 'codigo' => 'gerente']);

        $this->envio = DocumentEnvio::create([
            'sender_id' => $this->sender->id, 'titulo' => 'Reporte Mensual',
            'categoria_id' => $categoria->id, 'prioridad_id' => $prioridad->id,
            'estado_general' => 'pendiente', 'current_step_order' => 1,
        ]);
        DocumentEnvioStep::create(['envio_id' => $this->envio->id, 'orden' => 1, 'area_id' => $this->contabilidad->id, 'estado' => 'pendiente']);
        DocumentEnvioStep::create(['envio_id' => $this->envio->id, 'orden' => 2, 'area_id' => $this->gerencia->id, 'estado' => 'pendiente']);

        Storage::disk('public')->put('internal_docs/reporte.pdf', 'contenido-fake-pdf');
        $this->file = DocumentEnvioFile::create([
            'envio_id' => $this->envio->id, 'path' => 'internal_docs/reporte.pdf', 'original_name' => 'reporte.pdf',
        ]);
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

    private function downloadUrl(): string
    {
        return "/api/document-envios/{$this->envio->id}/files/{$this->file->id}/download";
    }

    public function test_sender_can_download_file(): void
    {
        Passport::actingAs($this->sender);
        $response = $this->get($this->downloadUrl());
        $response->assertStatus(200);
    }

    public function test_current_step_area_can_download_file(): void
    {
        $contable = $this->makeUser('contable', 'c1');
        Passport::actingAs($contable);
        $response = $this->get($this->downloadUrl());
        $response->assertStatus(200);
    }

    public function test_future_step_area_cannot_download_yet(): void
    {
        $gerente = $this->makeUser('gerente', 'g1');
        Passport::actingAs($gerente);
        $response = $this->get($this->downloadUrl());
        $response->assertStatus(403);
    }

    public function test_area_outside_route_cannot_download(): void
    {
        $coordinador = $this->makeUser('coordinador_comercial', 'cc1');
        Passport::actingAs($coordinador);
        $response = $this->get($this->downloadUrl());
        $response->assertStatus(403);
    }

    public function test_superadmin_can_download(): void
    {
        $admin = $this->makeUser('superadmin', 'sa1');
        Passport::actingAs($admin);
        $response = $this->get($this->downloadUrl());
        $response->assertStatus(200);
    }

    public function test_file_not_belonging_to_envio_returns_404(): void
    {
        $otroEnvio = DocumentEnvio::create([
            'sender_id' => $this->sender->id, 'titulo' => 'Otro',
            'categoria_id' => $this->envio->categoria_id, 'prioridad_id' => $this->envio->prioridad_id,
            'estado_general' => 'pendiente', 'current_step_order' => 1,
        ]);

        Passport::actingAs($this->sender);
        $response = $this->get("/api/document-envios/{$otroEnvio->id}/files/{$this->file->id}/download");
        $response->assertStatus(404);
    }
}
