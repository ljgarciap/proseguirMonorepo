<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\ClientUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ClientUploadTest extends TestCase
{
    use RefreshDatabase;

    private $cliente;
    private $docCC;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);

        $this->cliente = User::create([
            'name' => 'Cliente Test',
            'email' => 'cliente.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '111111',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['cliente']
        ]);
    }

    public function test_client_can_upload_file_with_category(): void
    {
        Passport::actingAs($this->cliente);

        $file = UploadedFile::fake()->create('documento.pdf', 500, 'application/pdf');

        $response = $this->postJson('/api/uploads', [
            'file' => $file,
            'active_role' => 'cliente',
            'category' => 'mandatos'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('client_uploads', [
            'user_id' => $this->cliente->id,
            'category' => 'mandatos',
            'original_name' => 'documento.pdf'
        ]);
    }
}
