<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\DocumentArea;
use App\Models\DocumentEnvio;
use App\Models\InternalDocument;
use App\Models\AccountingCategory;
use App\Models\AccountingPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrateLegacyInternalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private $sender;
    private $categoria;
    private $prioridad;

    protected function setUp(): void
    {
        parent::setUp();

        $docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);
        $this->sender = User::create([
            'name' => 'Operativo Sender',
            'email' => 'sender@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '999999',
            'tipo_documento_id' => $docCC->id,
            'roles' => ['operativo'],
        ]);
        $this->categoria = AccountingCategory::create(['nombre' => 'Extractos Bancarios']);
        $this->prioridad = AccountingPriority::create(['nombre' => 'Baja']);

        DocumentArea::create(['nombre' => 'Contabilidad', 'codigo' => 'contable']);
        DocumentArea::create(['nombre' => 'Gerencia', 'codigo' => 'gerente']);
    }

    private function makeLegacyDoc(array $overrides = []): InternalDocument
    {
        return InternalDocument::create(array_merge([
            'sender_id' => $this->sender->id,
            'target_role' => 'contable',
            'titulo' => 'Doc Legacy',
            'archivo_path' => 'internal_docs/legacy.pdf',
            'original_name' => 'legacy.pdf',
            'categoria_id' => $this->categoria->id,
            'prioridad_id' => $this->prioridad->id,
            'estado' => 'pendiente',
        ], $overrides));
    }

    public function test_dry_run_does_not_write_anything(): void
    {
        $this->makeLegacyDoc();

        $this->artisan('app:migrate-legacy-internal-docs --dry-run')
            ->assertExitCode(0);

        $this->assertEquals(0, DocumentEnvio::count());
    }

    public function test_migrates_single_pending_document(): void
    {
        $doc = $this->makeLegacyDoc(['estado' => 'pendiente']);

        $this->artisan('app:migrate-legacy-internal-docs')->assertExitCode(0);

        $this->assertEquals(1, DocumentEnvio::count());
        $envio = DocumentEnvio::first();
        $this->assertEquals('pendiente', $envio->estado_general);
        $this->assertEquals($doc->titulo, $envio->titulo);
        $this->assertEquals(1, $envio->files()->count());
        $this->assertEquals(1, $envio->steps()->count());
        $this->assertEquals('contable', $envio->steps()->first()->area->codigo);
    }

    public function test_visto_bueno_maps_to_en_proceso(): void
    {
        $this->makeLegacyDoc(['estado' => 'visto_bueno', 'target_role' => 'gerente']);

        $this->artisan('app:migrate-legacy-internal-docs')->assertExitCode(0);

        $envio = DocumentEnvio::first();
        $this->assertEquals('en_proceso', $envio->estado_general);
        $this->assertEquals('en_proceso', $envio->steps()->first()->estado);
    }

    public function test_groups_batch_documents_into_a_single_envio_with_multiple_files(): void
    {
        $this->makeLegacyDoc(['batch_id' => 'batch_abc', 'original_name' => 'a.pdf']);
        $this->makeLegacyDoc(['batch_id' => 'batch_abc', 'original_name' => 'b.pdf']);
        $this->makeLegacyDoc(['batch_id' => 'batch_abc', 'original_name' => 'c.pdf']);

        $this->artisan('app:migrate-legacy-internal-docs')->assertExitCode(0);

        $this->assertEquals(1, DocumentEnvio::count());
        $this->assertEquals(3, DocumentEnvio::first()->files()->count());
    }

    public function test_procesado_documents_are_not_migrated(): void
    {
        $this->makeLegacyDoc(['estado' => 'procesado']);
        $this->makeLegacyDoc(['estado' => 'rechazado']);

        $this->artisan('app:migrate-legacy-internal-docs')->assertExitCode(0);

        $this->assertEquals(0, DocumentEnvio::count());
    }

    public function test_running_twice_does_not_duplicate(): void
    {
        $this->makeLegacyDoc();

        $this->artisan('app:migrate-legacy-internal-docs')->assertExitCode(0);
        $this->artisan('app:migrate-legacy-internal-docs')->assertExitCode(0);

        $this->assertEquals(1, DocumentEnvio::count());
    }

    public function test_original_internal_document_is_not_modified_or_deleted(): void
    {
        $doc = $this->makeLegacyDoc();

        $this->artisan('app:migrate-legacy-internal-docs')->assertExitCode(0);

        $this->assertDatabaseHas('internal_documents', [
            'id' => $doc->id,
            'estado' => 'pendiente',
        ]);
    }

    public function test_missing_area_is_reported_and_skipped(): void
    {
        $this->makeLegacyDoc(['target_role' => 'operativo']); // no existe DocumentArea con codigo=operativo en este test

        $this->artisan('app:migrate-legacy-internal-docs')->assertExitCode(0);

        $this->assertEquals(0, DocumentEnvio::count());
    }
}
