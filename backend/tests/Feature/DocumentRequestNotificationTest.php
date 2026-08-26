<?php

namespace Tests\Feature;

use App\Mail\CargaCompletaCoordinadorMail;
use App\Models\Cliente;
use App\Models\CreditoOrdinario;
use App\Models\DocumentPreset;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\TipoPersona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * SCRUM-252: notificación al Coordinador Comercial cuando el cliente
 * completa el cargue de todos los documentos requeridos de Etapa 1.
 *
 * Nota SCRUM-256 (comentario Juan Andrés, 2026-08-26): los fakes de item1 y
 * item2 usan createWithContent() con contenido distinto a propósito —
 * UploadedFile::fake()->create() genera un archivo físico vacío (0 bytes)
 * sin importar el nombre, y DuplicateDocumentGuard compara por hash de
 * contenido; con 2 fakes vacíos, el 2° upload se marcaría como duplicado
 * del 1° y estos tests fallarían con 422 en vez de 200.
 */
class DocumentRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    private $docCC;
    private $coordinador;
    private $clienteUser;
    private $cliente;
    private $documentRequest;
    private $item1;
    private $item2;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');

        $this->docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);

        $this->coordinador = User::create([
            'name' => 'Coordinador Comercial',
            'email' => 'coordinador.scrum252@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'coord252',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['coordinador_comercial'],
        ]);

        $this->cliente = Cliente::create([
            'tipo_persona_id' => $tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '252525',
            'identificacion' => '252525',
            'nombre' => 'Cliente Prueba 252',
            'nombres' => 'Cliente',
            'primer_apellido' => 'Prueba',
            'correo_electronico' => 'cliente252@test.com',
            'telefono' => '3000000000',
            'direccion' => 'Calle 252',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true,
        ]);

        $this->clienteUser = User::create([
            'name' => 'Cliente Prueba 252',
            'email' => 'cliente252@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '252525',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['cliente'],
        ]);

        $preset = DocumentPreset::create(['nombre' => 'Preset 252', 'descripcion' => 'Requisitos']);
        $req1 = DocumentRequirement::create(['nombre' => 'Documento 1', 'activo' => true]);
        $req2 = DocumentRequirement::create(['nombre' => 'Documento 2', 'activo' => true]);
        $preset->requirements()->attach([$req1->id, $req2->id]);

        $this->documentRequest = DocumentRequest::create([
            'cliente_id' => $this->clienteUser->id,
            'creado_por' => $this->coordinador->id,
            'estado' => 'pendiente',
            'etapa' => 'inicial',
            'preset_id' => $preset->id,
            'preset_nombre' => $preset->nombre,
        ]);

        $this->item1 = DocumentRequestItem::create([
            'document_request_id' => $this->documentRequest->id,
            'document_requirement_id' => $req1->id,
            'estado' => 'pendiente',
        ]);
        $this->item2 = DocumentRequestItem::create([
            'document_request_id' => $this->documentRequest->id,
            'document_requirement_id' => $req2->id,
            'estado' => 'pendiente',
        ]);
    }

    /**
     * Solicitud sin cliente asociado a SolicitudCredito (fixture mínima:
     * DocumentRequest suelto) — cubre el caso base del servicio sin acoplar
     * a todo el store() de SolicitudCreditoController.
     */
    private function asociarSolicitud(): void
    {
        $tipoCredito = \App\Models\TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
        $amortizacion = \App\Models\Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);

        $solicitud = \App\Models\SolicitudCredito::create([
            'cliente_id' => $this->cliente->id,
            'usuario_registra_id' => $this->coordinador->id,
            'tipo_credito_id' => $tipoCredito->id,
            'monto_solicitado' => 10000000,
            'plazo_meses' => 12,
            'amortizacion_id' => $amortizacion->id,
            'destino_recurso' => 'Capital',
            'fuente_pago' => 'Ventas',
            'correo_notificacion' => 'cliente252@test.com',
            'asunto_notificacion' => 'Asunto',
            'mensaje_notificacion' => 'Mensaje',
        ]);

        $this->documentRequest->update(['solicitud_credito_id' => $solicitud->id]);
    }

    public function test_notifica_al_coordinador_cuando_se_completa_el_ultimo_documento_via_mis_cargas(): void
    {
        $this->asociarSolicitud();
        Passport::actingAs($this->clienteUser);

        // Primer documento subido — todavía falta el 2°, no debe notificar.
        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->createWithContent('doc1.pdf', 'contenido de prueba doc1 SCRUM-252'),
            'active_role' => 'cliente',
            'document_request_item_id' => $this->item1->id,
        ])->assertStatus(200);

        Mail::assertNotSent(CargaCompletaCoordinadorMail::class);
        $this->assertNull($this->documentRequest->fresh()->notificado_completado_at);

        // Segundo (último) documento — ahora sí debe notificar al coordinador.
        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->createWithContent('doc2.pdf', 'contenido de prueba doc2 SCRUM-252'),
            'active_role' => 'cliente',
            'document_request_item_id' => $this->item2->id,
        ])->assertStatus(200);

        Mail::assertSent(CargaCompletaCoordinadorMail::class, function ($mail) {
            return $mail->hasTo('coordinador.scrum252@test.com')
                && $mail->origen === 'Mis Cargas'
                && $mail->solicitud->id === $this->documentRequest->fresh()->solicitud_credito_id;
        });

        $this->assertNotNull($this->documentRequest->fresh()->notificado_completado_at);
    }

    public function test_no_duplica_la_notificacion_en_una_validacion_posterior(): void
    {
        $this->asociarSolicitud();
        Passport::actingAs($this->clienteUser);

        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->createWithContent('doc1.pdf', 'contenido de prueba doc1 SCRUM-252'),
            'active_role' => 'cliente',
            'document_request_item_id' => $this->item1->id,
        ])->assertStatus(200);

        $upload = $this->item2->fresh();
        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->createWithContent('doc2.pdf', 'contenido de prueba doc2 SCRUM-252'),
            'active_role' => 'cliente',
            'document_request_item_id' => $this->item2->id,
        ])->assertStatus(200);

        Mail::assertSent(CargaCompletaCoordinadorMail::class, 1);

        // Una validación posterior del operativo sobre el mismo upload
        // (syncRequestItem) no debe reenviar el correo — alt. 4.2 de la spec.
        $operativo = User::create([
            'name' => 'Operativo Test',
            'email' => 'operativo.scrum252@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'oper252',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['operativo'],
        ]);

        $item2Actualizado = $this->item2->fresh();
        Passport::actingAs($operativo);
        $this->postJson("/api/uploads/{$item2Actualizado->client_upload_id}/validate", [
            'action' => 'validar',
        ])->assertStatus(200);

        Mail::assertSent(CargaCompletaCoordinadorMail::class, 1);
    }

    public function test_no_notifica_si_el_document_request_es_de_garantias(): void
    {
        $this->asociarSolicitud();
        $this->documentRequest->update(['etapa' => 'garantias']);
        Passport::actingAs($this->clienteUser);

        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->createWithContent('doc1.pdf', 'contenido de prueba doc1 SCRUM-252'),
            'active_role' => 'cliente',
            'document_request_item_id' => $this->item1->id,
        ])->assertStatus(200);
        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->createWithContent('doc2.pdf', 'contenido de prueba doc2 SCRUM-252'),
            'active_role' => 'cliente',
            'document_request_item_id' => $this->item2->id,
        ])->assertStatus(200);

        Mail::assertNotSent(CargaCompletaCoordinadorMail::class);
    }

    public function test_no_notifica_si_el_coordinador_no_tiene_correo_activo(): void
    {
        $this->coordinador->update(['email' => null]);
        $this->asociarSolicitud();
        Passport::actingAs($this->clienteUser);

        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->createWithContent('doc1.pdf', 'contenido de prueba doc1 SCRUM-252'),
            'active_role' => 'cliente',
            'document_request_item_id' => $this->item1->id,
        ])->assertStatus(200);
        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->createWithContent('doc2.pdf', 'contenido de prueba doc2 SCRUM-252'),
            'active_role' => 'cliente',
            'document_request_item_id' => $this->item2->id,
        ])->assertStatus(200);

        Mail::assertNotSent(CargaCompletaCoordinadorMail::class);
        // No debe marcarse como notificado si no había a quién avisar.
        $this->assertNull($this->documentRequest->fresh()->notificado_completado_at);
    }

    /**
     * RF-02: el mismo chequeo aplica también desde "Mis Créditos"
     * (CreditoOrdinarioController::transition(), campo_documento
     * 'req_item_{id}') — no solo desde "Mis Cargas".
     */
    public function test_notifica_al_coordinador_desde_mis_creditos(): void
    {
        $this->asociarSolicitud();
        $solicitudId = $this->documentRequest->fresh()->solicitud_credito_id;

        $credito = CreditoOrdinario::create([
            'numero_solicitud' => 'CO-SCRUM252-1',
            'cliente_id' => $this->clienteUser->id,
            'solicitud_credito_id' => $solicitudId,
            'monto' => 10000000,
            'plazo_meses' => 12,
            'estado' => 'revision_documental',
            'documentos' => [],
        ]);

        Passport::actingAs($this->clienteUser);

        $this->postJson("/api/creditos/{$credito->id}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'req_item_' . $this->item1->id,
            'archivos' => [UploadedFile::fake()->createWithContent('doc1.pdf', 'contenido de prueba doc1 SCRUM-252')],
        ], ['X-Active-Role' => 'cliente'])->assertStatus(200);

        Mail::assertNotSent(CargaCompletaCoordinadorMail::class);

        $this->postJson("/api/creditos/{$credito->id}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'req_item_' . $this->item2->id,
            'archivos' => [UploadedFile::fake()->createWithContent('doc2.pdf', 'contenido de prueba doc2 SCRUM-252')],
        ], ['X-Active-Role' => 'cliente'])->assertStatus(200);

        Mail::assertSent(CargaCompletaCoordinadorMail::class, function ($mail) {
            return $mail->hasTo('coordinador.scrum252@test.com') && $mail->origen === 'Mis Créditos';
        });
    }

    /**
     * SCRUM-256 (comentario Juan Andrés, 2026-08-26): un archivo cargado
     * para 'Documento 1' no debe poder registrarse también como 'Documento
     * 2' del mismo expediente — antes ningún guard lo evitaba, cada
     * documento quedaba individualmente "Cargado (1)" con el mismo archivo
     * físico. Origen "Mis Cargas" (ClientUploadController::store()).
     */
    public function test_no_permite_el_mismo_archivo_para_dos_documentos_distintos_via_mis_cargas(): void
    {
        Passport::actingAs($this->clienteUser);

        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->createWithContent('compraventa.pdf', 'mismo contenido físico'),
            'active_role' => 'cliente',
            'document_request_item_id' => $this->item1->id,
        ])->assertStatus(200);

        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->createWithContent('compraventa-renombrado.pdf', 'mismo contenido físico'),
            'active_role' => 'cliente',
            'document_request_item_id' => $this->item2->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Este archivo ya fue cargado como "Documento 1". Cada documento requiere un archivo distinto.');

        $this->item2->refresh();
        $this->assertSame('pendiente', $this->item2->estado);
        $this->assertNull($this->item2->client_upload_id);
    }
}
