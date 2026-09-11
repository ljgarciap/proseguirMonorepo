<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\CreditoOrdinario;
use App\Models\CreditoOrdinarioAnexo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * SCRUM-345 (rebote): "Anexos" — archivos sueltos sin preset ni clave
 * predefinida, cargados por el Director de Crédito y visibles para el
 * resto de roles internos con acceso al módulo. Ver
 * CreditoOrdinarioAnexoController.
 */
class CreditoOrdinarioAnexoTest extends TestCase
{
    use RefreshDatabase;

    private $cliente;
    private $coordinador;
    private $operativo;
    private $docCC;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);

        $this->cliente = User::create([
            'name' => 'Cliente Test',
            'email' => 'cliente.anexo@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '111222',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['cliente'],
        ]);

        $this->coordinador = User::create([
            'name' => 'Director Credito Test',
            'email' => 'director.anexo@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '222333',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['coordinador_comercial'],
        ]);

        $this->operativo = User::create([
            'name' => 'Operativo Test',
            'email' => 'operativo.anexo@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '333444',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['operativo'],
        ]);
    }

    private function creditoEnEstado(string $estado): int
    {
        $credito = CreditoOrdinario::iniciar(
            clienteId: $this->cliente->id,
            monto: 10000000,
            plazoMeses: 12,
            usuario: $this->coordinador->name,
            rol: 'coordinador_comercial',
            comentario: 'Solicitud registrada.',
        );
        $credito->update(['estado' => $estado]);

        return $credito->id;
    }

    public function test_director_credito_puede_cargar_anexo_en_cualquier_etapa(): void
    {
        // 'desembolso_ingreso' no tiene nada que ver con Etapa 1: los
        // anexos no están atados a ninguna etapa del BPMN (pedido explícito).
        $creditoId = $this->creditoEnEstado('desembolso_ingreso');

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/creditos/{$creditoId}/anexos", [
            'archivo' => UploadedFile::fake()->create('valoracion cliente.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(201)
            ->assertJsonPath('nombre_original', 'valoracion cliente.docx')
            ->assertJsonPath('subido_por_rol', 'coordinador_comercial');

        $this->assertSame(1, CreditoOrdinarioAnexo::where('credito_ordinario_id', $creditoId)->count());
    }

    public function test_anexo_acepta_imagen_sin_restringirse_a_pdf(): void
    {
        $creditoId = $this->creditoEnEstado('revision_documental');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/creditos/{$creditoId}/anexos", [
            'archivo' => UploadedFile::fake()->create('tirilla.jpg', 200, 'image/jpeg'),
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(201);
    }

    public function test_2_anexos_con_el_mismo_nombre_de_archivo_no_se_pisan(): void
    {
        $creditoId = $this->creditoEnEstado('revision_documental');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/creditos/{$creditoId}/anexos", [
            'archivo' => UploadedFile::fake()->create('IMG_0001.jpg', 100, 'image/jpeg'),
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(201);

        $this->postJson("/api/creditos/{$creditoId}/anexos", [
            'archivo' => UploadedFile::fake()->create('IMG_0001.jpg', 100, 'image/jpeg'),
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(201);

        $anexos = CreditoOrdinarioAnexo::where('credito_ordinario_id', $creditoId)->get();
        $this->assertCount(2, $anexos);
        $this->assertNotSame($anexos[0]->path, $anexos[1]->path);
        // Ambos conservan el nombre original mostrado al usuario.
        $this->assertTrue($anexos->every(fn ($a) => $a->nombre_original === 'IMG_0001.jpg'));
    }

    public function test_rol_distinto_de_director_credito_no_puede_cargar_anexo(): void
    {
        $creditoId = $this->creditoEnEstado('desembolso_ingreso');

        Passport::actingAs($this->operativo);
        $this->postJson("/api/creditos/{$creditoId}/anexos", [
            'archivo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['X-Active-Role' => 'operativo'])->assertStatus(403);

        $this->assertSame(0, CreditoOrdinarioAnexo::where('credito_ordinario_id', $creditoId)->count());
    }

    public function test_cliente_no_puede_cargar_ni_ver_anexos(): void
    {
        $creditoId = $this->creditoEnEstado('desembolso_ingreso');

        Passport::actingAs($this->cliente);
        $this->postJson("/api/creditos/{$creditoId}/anexos", [
            'archivo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['X-Active-Role' => 'cliente'])->assertStatus(403);

        $this->get("/api/creditos/{$creditoId}/anexos", ['X-Active-Role' => 'cliente'])->assertStatus(403);
    }

    public function test_rol_interno_que_no_es_director_credito_puede_ver_y_descargar_anexos(): void
    {
        $creditoId = $this->creditoEnEstado('revision_documental');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/creditos/{$creditoId}/anexos", [
            'archivo' => UploadedFile::fake()->create('externo.pdf', 100, 'application/pdf'),
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(201);

        Passport::actingAs($this->operativo);
        $response = $this->getJson("/api/creditos/{$creditoId}/anexos", ['X-Active-Role' => 'operativo']);

        $response->assertStatus(200)->assertJsonCount(1);
        $this->assertNotNull($response->json('0.url'));
    }
}
