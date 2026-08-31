<?php

namespace Tests\Feature;

use App\Mail\DesembolsoAprobadoOperativoMail;
use App\Mail\DesembolsoAprobadoTesoreriaMail;
use App\Mail\DesembolsoRechazadoOperativoMail;
use App\Mail\DesembolsoRegistradoMail;
use App\Mail\FormalizacionGarantiasCoordinadorMail;
use App\Mail\FormalizacionGarantiasPendienteOperativoMail;
use App\Mail\FormalizacionGarantiasResultadoMail;
use App\Mail\GestionCreditoNotificacionMail;
use App\Mail\RegistroCyfAprobadoCoordinadorMail;
use App\Mail\RegistroCyfAprobadoMail;
use App\Mail\RegistroCyfPendienteAprobacionMail;
use App\Mail\RegistroCyfRechazadoCoordinadorMail;
use App\Mail\TransferenciaRealizadaClienteMail;
use App\Mail\TransferenciaRegistradaInternaMail;
use App\Models\ActivityLog;
use App\Models\Amortizacion;
use App\Models\ClientUpload;
use App\Models\Cliente;
use App\Models\CreditoOrdinario;
use App\Models\DocumentPreset;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\EntidadBancaria;
use App\Models\SolicitudCredito;
use App\Models\TipoCredito;
use App\Models\TipoPersona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * SCRUM-178 — Gestión de Créditos: bandeja, tarjetas y "Registrar y enviar
 * notificación" para los 4 resultados (Garantías, SARLAFT desfavorable,
 * Rechazada por Comité, Pendiente por Comité).
 */
class GestionCreditoTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $coordinador;
    private $comite;
    private $gerente;
    private $operativo;
    private $tesoreria;
    private $entidadBancaria;
    private $docCC;
    private $tipoOrdinario;
    private $amortizacionMensual;
    private $clienteNatural;
    private $preset;
    private $presetDesembolso;
    private $reqPagare;
    private $reqComprobante;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake();

        $this->docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
        $this->tipoOrdinario = TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
        $this->amortizacionMensual = Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);

        $this->clienteNatural = Cliente::create([
            'tipo_persona_id' => $tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '90807060',
            'identificacion' => '90807060',
            'nombre' => 'Gestion Test',
            'nombres' => 'Gestion',
            'primer_apellido' => 'Test',
            'correo_electronico' => 'gestion.cliente@test.com',
            'telefono' => '3005550000',
            'direccion' => 'Calle Gestión 1',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Super Administrador', 'email' => 'admin.gc@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'admin_gc', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['superadmin'],
        ]);

        $this->coordinador = User::create([
            'name' => 'Coordinador Test', 'email' => 'coordinador.gc@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'coord_gc', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['coordinador_comercial'],
        ]);

        $this->comite = User::create([
            'name' => 'Comite Test', 'email' => 'comite.gc@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'comite_gc', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['comite_credito'],
        ]);

        // SCRUM-211/215/219
        $this->gerente = User::create([
            'name' => 'Gerente Test', 'email' => 'gerente.gc@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'gerente_gc', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['gerente'],
        ]);

        $this->operativo = User::create([
            'name' => 'Operativo Test', 'email' => 'operativo.gc@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'operativo_gc', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['operativo'],
        ]);

        $this->tesoreria = User::create([
            'name' => 'Tesoreria Test', 'email' => 'tesoreria.gc@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'tesoreria_gc', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['tesoreria'],
        ]);

        $this->preset = DocumentPreset::create(['nombre' => 'Preset Garantías', 'descripcion' => 'Documentos de garantías']);
        $requirement = DocumentRequirement::create(['nombre' => 'Pagaré firmado', 'activo' => true]);
        $this->preset->requirements()->attach([$requirement->id]);

        // SCRUM-215: preset de desembolso con 2 documentos, para probar que
        // TODOS son obligatorios.
        $this->presetDesembolso = DocumentPreset::create(['nombre' => 'Preset Desembolso', 'descripcion' => 'Documentos de desembolso']);
        $this->reqPagare = DocumentRequirement::create(['nombre' => 'Pagaré', 'activo' => true]);
        $this->reqComprobante = DocumentRequirement::create(['nombre' => 'Comprobante de Egreso', 'activo' => true]);
        $this->presetDesembolso->requirements()->attach([$this->reqPagare->id, $this->reqComprobante->id]);

        // SCRUM-224
        $this->entidadBancaria = EntidadBancaria::create(['nombre' => 'Bancolombia Test']);
    }

    private function crearSolicitud(string $sufijo): SolicitudCredito
    {
        return SolicitudCredito::create([
            'cliente_id' => $this->clienteNatural->id,
            'usuario_registra_id' => $this->admin->id,
            'tipo_credito_id' => $this->tipoOrdinario->id,
            'monto_solicitado' => 30000000,
            'plazo_meses' => 12,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Capital de trabajo',
            'garantia' => 'Pagaré',
            'fuente_pago' => 'Ingresos operacionales',
            'correo_notificacion' => 'gestion.cliente@test.com',
            'asunto_notificacion' => 'Documentación',
            'mensaje_notificacion' => 'Adjunta los archivos.',
        ]);
    }

    private function crearCredito(string $estado, ?string $resultadoOrigen, string $sufijo): CreditoOrdinario
    {
        $solicitud = $this->crearSolicitud($sufijo);

        return CreditoOrdinario::create([
            'numero_solicitud' => 'CO-GC-' . $sufijo,
            'cliente_id' => $this->clienteNatural->id,
            'solicitud_credito_id' => $solicitud->id,
            'monto' => 30000000,
            'plazo_meses' => 12,
            'estado' => $estado,
            'resultado_origen' => $resultadoOrigen,
            'documentos' => $estado === 'rechazado' && $resultadoOrigen === 'sarlaft'
                ? ['sintesis_oficial_cumplimiento' => 'credito_documentos/sintesis.pdf']
                : [],
        ]);
    }

    // ---- Bandeja y tarjetas ---------------------------------------------

    public function test_tarjetas_cuentan_solo_solicitudes_no_gestionadas(): void
    {
        $this->crearCredito('aprobada_garantias', 'comite_aprobado', '1');
        $gestionado = $this->crearCredito('aprobada_garantias', 'comite_aprobado', '2');
        $gestionado->update(['solicitud_gestionada' => true, 'fecha_gestion' => now()]);
        $this->crearCredito('rechazado', 'sarlaft', '3');
        $this->crearCredito('rechazado', 'comite_rechazado', '4');
        $this->crearCredito('pendiente_comite', 'comite_pendiente', '5');
        // No debe aparecer: rechazo por otra vía del BPMN, sin resultado_origen.
        $this->crearCredito('rechazado', null, '6');

        Passport::actingAs($this->coordinador);
        $response = $this->getJson('/api/gestion-creditos/tarjetas', ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200)->assertJson([
            'sarlaft_desfavorable' => 1,
            'aprobada_garantias' => 1,
            'rechazada_comite' => 1,
            'pendiente_comite' => 1,
        ]);
    }

    public function test_bandeja_excluye_rechazos_sin_resultado_origen(): void
    {
        $this->crearCredito('rechazado', null, '1');

        Passport::actingAs($this->coordinador);
        $response = $this->getJson('/api/gestion-creditos', ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200)->assertJsonCount(0);
    }

    public function test_bandeja_filtra_por_estado_y_gestionada(): void
    {
        $this->crearCredito('aprobada_garantias', 'comite_aprobado', '1');
        $this->crearCredito('rechazado', 'sarlaft', '2');

        Passport::actingAs($this->coordinador);
        $response = $this->getJson('/api/gestion-creditos?estado=sarlaft_desfavorable', ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200)->assertJsonCount(1)
            ->assertJsonPath('0.resultado_origen', 'sarlaft');
    }

    public function test_roles_fuera_de_alcance_reciben_403(): void
    {
        $this->crearCredito('aprobada_garantias', 'comite_aprobado', '1');

        Passport::actingAs($this->comite);
        $this->getJson('/api/gestion-creditos', ['X-Active-Role' => 'comite_credito'])->assertStatus(403);
    }

    // ---- notificar(): Aprobada para gestión de garantías -----------------

    public function test_notificar_aprobada_garantias_requiere_preset(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', '1');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Aprobación de garantías',
            'mensaje' => 'Debe formalizar las garantías.',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Seleccione la documentación requerida.']);

        Mail::assertNotSent(GestionCreditoNotificacionMail::class);
    }

    /**
     * SCRUM-193/205 (2026-08-17): ya no transiciona a 'formalizacion_garantias'
     * (legacy, rol Operativo) — se queda en 'aprobada_garantias' mientras el
     * cliente diligencia el preset, y crea el DocumentRequest que antes
     * faltaba (ver docblock de notificar()).
     */
    public function test_notificar_aprobada_garantias_crea_document_request_y_mantiene_estado(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', '1');

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Aprobación de garantías',
            'mensaje' => 'Debe formalizar las garantías.',
            'preset_id' => $this->preset->id,
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'aprobada_garantias');

        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'aprobada_garantias',
            'solicitud_gestionada' => true,
        ]);
        $this->assertNotNull($credito->fresh()->fecha_gestion);

        $this->assertDatabaseHas('document_requests', [
            'cliente_id' => $this->clienteNatural->id,
            'solicitud_credito_id' => $credito->solicitud_credito_id,
            'estado' => 'pendiente',
            'etapa' => 'garantias',
            'preset_id' => $this->preset->id,
            'preset_nombre' => $this->preset->nombre,
        ]);

        Mail::assertSent(GestionCreditoNotificacionMail::class, function ($mail) use ($credito) {
            return $mail->hasTo('gestion.cliente@test.com')
                && $mail->credito->id === $credito->id
                && $mail->asuntoCorreo === 'Aprobación de garantías';
        });
    }

    /**
     * SCRUM-229: Crédito Ordinario (Etapa 4) necesita distinguir el
     * DocumentRequest de garantías del de Etapa 1 (mismo
     * solicitud_credito_id) para mostrar el preset y los documentos reales
     * que el Coordinador Comercial solicitó — se apoya en la columna
     * 'etapa' y en la relación SolicitudCredito::garantiasDocumentRequest().
     */
    public function test_garantias_document_request_se_distingue_del_de_etapa_1(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', '1');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Aprobación de garantías',
            'mensaje' => 'Debe formalizar las garantías.',
            'preset_id' => $this->preset->id,
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(200);

        $solicitud = $credito->solicitudCredito()->first();
        $garantiasRequest = $solicitud->garantiasDocumentRequest()->first();

        $this->assertNotNull($garantiasRequest);
        $this->assertSame('garantias', $garantiasRequest->etapa);
        $this->assertSame($this->preset->id, $garantiasRequest->preset_id);
        $this->assertSame($this->preset->nombre, $garantiasRequest->preset_nombre);
        $this->assertGreaterThan(0, $garantiasRequest->items()->count());
    }

    /**
     * SCRUM-223 (2026-08-18): un crédito que llega a comité casi siempre
     * arrastra una DocumentRequest vieja y todavía 'pendiente' de una etapa
     * anterior (ej. onboarding inicial) con documentos que no tienen nada
     * que ver con el preset de garantías que el Coordinador pide ahora. El
     * guard de duplicados de crearSolicitudDocumentos() la veía como "ya
     * hay una pendiente para este crédito" y no creaba la nueva — el
     * cliente se quedaba viendo solo los documentos viejos, sin relación
     * con lo recién solicitado, y el crédito nunca avanzaba.
     */
    public function test_notificar_aprobada_garantias_crea_document_request_aunque_haya_una_pendiente_de_otra_etapa(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', '1');

        $requirementOnboarding = DocumentRequirement::create(['nombre' => 'Aviso de privacidad', 'activo' => true]);
        $requestVieja = DocumentRequest::create([
            'cliente_id' => $this->clienteNatural->id,
            'creado_por' => $this->admin->id,
            'solicitud_credito_id' => $credito->solicitud_credito_id,
            'estado' => 'pendiente',
        ]);
        DocumentRequestItem::create([
            'document_request_id' => $requestVieja->id,
            'document_requirement_id' => $requirementOnboarding->id,
            'estado' => 'subido',
        ]);

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Aprobación de garantías',
            'mensaje' => 'Debe formalizar las garantías.',
            'preset_id' => $this->preset->id,
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200);

        $this->assertDatabaseCount('document_requests', 2);
        $nueva = DocumentRequest::where('solicitud_credito_id', $credito->solicitud_credito_id)
            ->where('id', '!=', $requestVieja->id)
            ->first();
        $this->assertNotNull($nueva, 'Debe crearse una nueva DocumentRequest para el preset de garantías.');
        $this->assertDatabaseHas('document_request_items', [
            'document_request_id' => $nueva->id,
            'document_requirement_id' => $this->preset->requirements()->first()->id,
        ]);
    }

    // ---- notificar(): SARLAFT desfavorable / Rechazada por Comité --------

    public function test_notificar_sarlaft_desfavorable_mantiene_estado_rechazado(): void
    {
        $credito = $this->crearCredito('rechazado', 'sarlaft', '1');

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Resultado de su solicitud',
            'mensaje' => 'Su solicitud no continúa en el proceso.',
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'rechazado');
        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'rechazado',
            'solicitud_gestionada' => true,
        ]);
    }

    public function test_notificar_sarlaft_sin_sintesis_falla_val06(): void
    {
        $credito = $this->crearCredito('rechazado', 'sarlaft', '1');
        $credito->update(['documentos' => []]);

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Resultado',
            'mensaje' => 'Mensaje.',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJson(['message' => 'No fue posible consultar la síntesis de validación. Intente nuevamente o contacte al administrador.']);
    }

    public function test_notificar_rechazada_comite_mantiene_estado_rechazado(): void
    {
        $credito = $this->crearCredito('rechazado', 'comite_rechazado', '1');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Decisión del Comité',
            'mensaje' => 'Su solicitud fue rechazada por el Comité.',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200)
            ->assertJsonPath('credito.estado', 'rechazado');
    }

    // ---- notificar(): Pendiente por Comité --------------------------------

    public function test_notificar_pendiente_comite_sin_responder_requiere_documentos_falla_val05(): void
    {
        $credito = $this->crearCredito('pendiente_comite', 'comite_pendiente', '1');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Solicitud aplazada',
            'mensaje' => 'El Comité aplazó su decisión.',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Indique si el cliente debe enviar documentación.']);
    }

    public function test_notificar_pendiente_comite_requiere_documentos_sin_preset_falla_val04(): void
    {
        $credito = $this->crearCredito('pendiente_comite', 'comite_pendiente', '1');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Solicitud aplazada',
            'mensaje' => 'El Comité aplazó su decisión.',
            'requiere_documentos' => true,
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Seleccione la documentación requerida.']);
    }

    public function test_notificar_pendiente_comite_requiere_documentos_crea_document_request(): void
    {
        $credito = $this->crearCredito('pendiente_comite', 'comite_pendiente', '1');

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Solicitud aplazada',
            'mensaje' => 'El Comité aplazó su decisión.',
            'requiere_documentos' => true,
            'preset_id' => $this->preset->id,
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'pendiente_comite');

        $this->assertDatabaseHas('document_requests', [
            'cliente_id' => $this->clienteNatural->id,
            'estado' => 'pendiente',
        ]);
    }

    /**
     * SCRUM-199 (2026-08-18): mismo bug que el de aprobada_garantias
     * (SCRUM-223) pero para el reenvío de "Pendiente por Comité" — una
     * DocumentRequest vieja de otra etapa, todavía 'pendiente', bloqueaba
     * en silencio la creación de la nueva con el preset recién elegido.
     */
    public function test_notificar_pendiente_comite_requiere_documentos_crea_document_request_aunque_haya_una_pendiente_de_otra_etapa(): void
    {
        $credito = $this->crearCredito('pendiente_comite', 'comite_pendiente', '1');

        $requirementOnboarding = DocumentRequirement::create(['nombre' => 'RUT', 'activo' => true]);
        $requestVieja = DocumentRequest::create([
            'cliente_id' => $this->clienteNatural->id,
            'creado_por' => $this->admin->id,
            'solicitud_credito_id' => $credito->solicitud_credito_id,
            'estado' => 'pendiente',
        ]);
        DocumentRequestItem::create([
            'document_request_id' => $requestVieja->id,
            'document_requirement_id' => $requirementOnboarding->id,
            'estado' => 'subido',
        ]);

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Solicitud aplazada',
            'mensaje' => 'El Comité aplazó su decisión.',
            'requiere_documentos' => true,
            'preset_id' => $this->preset->id,
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'pendiente_comite');

        $this->assertDatabaseCount('document_requests', 2);
        $nueva = DocumentRequest::where('solicitud_credito_id', $credito->solicitud_credito_id)
            ->where('id', '!=', $requestVieja->id)
            ->first();
        $this->assertNotNull($nueva, 'Debe crearse una nueva DocumentRequest para el preset recién elegido.');
        $this->assertDatabaseHas('document_request_items', [
            'document_request_id' => $nueva->id,
            'document_requirement_id' => $this->preset->requirements()->first()->id,
        ]);
    }

    /**
     * SCRUM-191 (2026-08-12, punto 1): sin documentos que esperar del
     * cliente, el crédito no tiene por qué quedarse estancado en
     * pendiente_comite — vuelve directo a la cola del Comité.
     */
    public function test_notificar_pendiente_comite_sin_requerir_documentos_vuelve_a_comite_evaluacion(): void
    {
        $credito = $this->crearCredito('pendiente_comite', 'comite_pendiente', '1');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Solicitud aplazada',
            'mensaje' => 'El Comité aplazó su decisión.',
            'requiere_documentos' => false,
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200)
            ->assertJsonPath('credito.estado', 'comite_evaluacion');

        $this->assertDatabaseCount('document_requests', 0);
        $credito->refresh();
        $this->assertTrue($credito->solicitud_gestionada);
    }

    // ---- Validaciones comunes ---------------------------------------------

    public function test_notificar_sin_destino_falla_val01(): void
    {
        $credito = $this->crearCredito('rechazado', 'comite_rechazado', '1');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'asunto' => 'Asunto',
            'mensaje' => 'Mensaje',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Ingrese una dirección de correo electrónico válida.']);
    }

    public function test_notificar_sin_asunto_falla_val02(): void
    {
        $credito = $this->crearCredito('rechazado', 'comite_rechazado', '1');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'mensaje' => 'Mensaje',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Ingrese el asunto del correo.']);
    }

    public function test_notificar_sin_mensaje_falla_val03(): void
    {
        $credito = $this->crearCredito('rechazado', 'comite_rechazado', '1');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Asunto',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Ingrese el mensaje de acompañamiento.']);
    }

    public function test_notificar_con_fallo_de_envio_no_gestiona_la_solicitud_val07(): void
    {
        $credito = $this->crearCredito('rechazado', 'comite_rechazado', '1');

        Mail::shouldReceive('to')
            ->once()
            ->with('gestion.cliente@test.com')
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('SMTP no disponible'));

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Decisión del Comité',
            'mensaje' => 'Su solicitud fue rechazada por el Comité.',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJson(['message' => 'La notificación no pudo enviarse. La solicitud continúa pendiente de gestión.']);

        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'rechazado',
            'solicitud_gestionada' => false,
        ]);
        $this->assertNull($credito->fresh()->fecha_gestion);
    }

    public function test_notificar_sobre_solicitud_sin_resultado_pendiente_falla(): void
    {
        $credito = $this->crearCredito('formalizacion_garantias', null, '1');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Asunto',
            'mensaje' => 'Mensaje',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Esta solicitud no tiene un resultado pendiente de gestión.']);
    }

    // ---- Integración con Actas de Comité (SCRUM-178 §2) --------------------

    public function test_flujo_completo_acta_aprobada_hasta_gestion(): void
    {
        // 1. Comité aprueba vía Acta → CreditoOrdinario pasa a aprobada_garantias.
        $solicitud = $this->crearSolicitud('acta1');
        $credito = CreditoOrdinario::create([
            'numero_solicitud' => 'CO-GC-ACTA-1',
            'cliente_id' => $this->clienteNatural->id,
            'solicitud_credito_id' => $solicitud->id,
            'monto' => 30000000,
            'plazo_meses' => 12,
            'estado' => 'comite_evaluacion',
            'documentos' => [],
        ]);

        Passport::actingAs($this->coordinador);
        $headers = ['X-Active-Role' => 'coordinador_comercial'];

        $acta = $this->postJson('/api/actas-comite/generar', [], $headers)->json();
        $actaId = $acta['id'];
        $solicitudActaId = $acta['solicitudes'][0]['id'];

        $this->putJson("/api/actas-comite/{$actaId}", [
            'fecha_reunion' => '2026-08-04',
            'lugar' => '<p>Sala de juntas</p>',
            'hora_inicio' => '09:00',
            'asistentes' => [['nombre' => 'Juan Pérez']],
        ], $headers)->assertStatus(200);

        $this->postJson("/api/actas-comite/{$actaId}/aprobar-orden-dia", [], $headers)->assertStatus(200);

        $this->putJson("/api/actas-comite/{$actaId}/solicitudes/{$solicitudActaId}", [
            'estado_decision' => 'aprobado',
            'monto_decision' => 30000000,
            'tasa_interes' => 1.5,
            'porcentaje_financiacion' => 80,
        ], $headers)->assertStatus(200);

        $this->putJson("/api/actas-comite/{$actaId}", [
            'observaciones_generales' => '<p>Sin observaciones.</p>',
            'hora_finalizacion' => '10:30',
            'firmantes' => [['nombre' => 'Juan Pérez', 'rol' => 'Presidente']],
        ], $headers)->assertStatus(200);

        $this->postJson("/api/actas-comite/{$actaId}/registrar", [], $headers)->assertStatus(200);

        $credito->refresh();
        $this->assertSame('aprobada_garantias', $credito->estado);
        $this->assertSame('comite_aprobado', $credito->resultado_origen);
        $this->assertFalse($credito->solicitud_gestionada);
        $this->assertNotEmpty($credito->documentos['acta_comite_firmada'] ?? null);

        // 2. Aparece en la bandeja de Gestión de Créditos con fecha_validacion
        // = fecha de la reunión del acta.
        $bandeja = $this->getJson('/api/gestion-creditos', $headers);
        $bandeja->assertStatus(200)->assertJsonCount(1)
            ->assertJsonPath('0.fecha_validacion', '2026-08-04');

        // 3. Coordinador gestiona: crea el DocumentRequest de garantías y se
        // queda en 'aprobada_garantias' (SCRUM-193/205) mientras el cliente
        // diligencia el preset.
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Aprobación de garantías',
            'mensaje' => 'Debe formalizar las garantías.',
            'preset_id' => $this->preset->id,
        ], $headers)->assertStatus(200)->assertJsonPath('credito.estado', 'aprobada_garantias');

        // 4. SCRUM-190 (2026-08-12): el crédito NO debe desaparecer de la
        // bandeja al gestionarse — debe seguir visible con
        // solicitud_gestionada=true y su fecha_gestion.
        $bandejaPostGestion = $this->getJson('/api/gestion-creditos', $headers);
        $bandejaPostGestion->assertStatus(200)->assertJsonCount(1)
            ->assertJsonPath('0.solicitud_gestionada', true)
            ->assertJsonPath('0.estado', 'aprobada_garantias');
        $this->assertNotNull($bandejaPostGestion->json('0.fecha_gestion'));
    }

    // ---- SCRUM-191 (2026-08-12, punto 1): documentos reenviados ----------

    private function notificarPendienteComiteConDocumentos(CreditoOrdinario $credito, array $headers): void
    {
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Solicitud aplazada',
            'mensaje' => 'El Comité aplazó su decisión, envíe la documentación solicitada.',
            'requiere_documentos' => true,
            'preset_id' => $this->preset->id,
        ], $headers)->assertStatus(200);
    }

    public function test_documentos_pendientes_devuelve_el_document_request_del_credito(): void
    {
        $credito = $this->crearCredito('pendiente_comite', 'comite_pendiente', '1');
        $headers = ['X-Active-Role' => 'coordinador_comercial'];
        Passport::actingAs($this->coordinador);

        $this->notificarPendienteComiteConDocumentos($credito, $headers);

        $response = $this->getJson("/api/gestion-creditos/{$credito->id}/documentos", $headers);
        $response->assertStatus(200)
            ->assertJsonPath('solicitud_credito_id', $credito->solicitud_credito_id)
            ->assertJsonCount(1, 'items');
    }

    public function test_revisar_documento_aprueba_item_y_vuelve_a_comite_al_completar_todos(): void
    {
        $credito = $this->crearCredito('pendiente_comite', 'comite_pendiente', '1');
        $headers = ['X-Active-Role' => 'coordinador_comercial'];
        Passport::actingAs($this->coordinador);

        $this->notificarPendienteComiteConDocumentos($credito, $headers);

        $documentRequest = DocumentRequest::where('solicitud_credito_id', $credito->solicitud_credito_id)->first();
        $item = $documentRequest->items()->first();

        // El cliente carga el documento (simulado directo en BD, el upload
        // en sí no es responsabilidad de este endpoint).
        $upload = ClientUpload::create([
            'user_id' => $credito->cliente_id,
            'upload_role' => 'cliente',
            'filename' => 'client_uploads/pagare.pdf',
            'original_name' => 'pagare.pdf',
            'status' => 'pendiente',
        ]);
        $item->update(['client_upload_id' => $upload->id, 'estado' => 'subido']);

        $response = $this->postJson(
            "/api/gestion-creditos/{$credito->id}/documentos/{$item->id}/revisar",
            ['accion' => 'aprobar'],
            $headers
        );

        $response->assertStatus(200)->assertJsonPath('credito_disponible_comite', true);

        $item->refresh();
        $this->assertSame('aprobado', $item->estado);
        $upload->refresh();
        $this->assertSame('aprobado', $upload->status);

        $documentRequest->refresh();
        $this->assertSame('completado', $documentRequest->estado);

        $credito->refresh();
        // SCRUM-236: vuelve a comite_evaluacion (no salta a aprobada_garantias)
        // para que quede disponible en el pool de una nueva Acta de Comité —
        // mismo filtro que ActaComiteController@creditosElegibles.
        $this->assertSame('comite_evaluacion', $credito->estado);
        $this->assertNull($credito->resultado_origen);
        $this->assertFalse($credito->solicitud_gestionada);
    }

    public function test_revisar_documento_rechaza_item_credito_sigue_pendiente_comite(): void
    {
        $credito = $this->crearCredito('pendiente_comite', 'comite_pendiente', '1');
        $headers = ['X-Active-Role' => 'coordinador_comercial'];
        Passport::actingAs($this->coordinador);

        $this->notificarPendienteComiteConDocumentos($credito, $headers);

        $documentRequest = DocumentRequest::where('solicitud_credito_id', $credito->solicitud_credito_id)->first();
        $item = $documentRequest->items()->first();
        $upload = ClientUpload::create([
            'user_id' => $credito->cliente_id,
            'upload_role' => 'cliente',
            'filename' => 'client_uploads/pagare.pdf',
            'original_name' => 'pagare.pdf',
            'status' => 'pendiente',
        ]);
        $item->update(['client_upload_id' => $upload->id, 'estado' => 'subido']);

        $response = $this->postJson(
            "/api/gestion-creditos/{$credito->id}/documentos/{$item->id}/revisar",
            ['accion' => 'rechazar', 'observaciones' => 'El documento no corresponde al pagaré solicitado.'],
            $headers
        );

        $response->assertStatus(200)->assertJsonPath('credito_disponible_comite', false);

        $item->refresh();
        $this->assertSame('rechazado', $item->estado);
        $this->assertSame('El documento no corresponde al pagaré solicitado.', $item->observaciones);

        $credito->refresh();
        $this->assertSame('pendiente_comite', $credito->estado);
    }

    public function test_revisar_documento_sin_cargar_archivo_falla(): void
    {
        $credito = $this->crearCredito('pendiente_comite', 'comite_pendiente', '1');
        $headers = ['X-Active-Role' => 'coordinador_comercial'];
        Passport::actingAs($this->coordinador);

        $this->notificarPendienteComiteConDocumentos($credito, $headers);

        $documentRequest = DocumentRequest::where('solicitud_credito_id', $credito->solicitud_credito_id)->first();
        $item = $documentRequest->items()->first();

        $this->postJson(
            "/api/gestion-creditos/{$credito->id}/documentos/{$item->id}/revisar",
            ['accion' => 'aprobar'],
            $headers
        )->assertStatus(422);
    }

    // ---- SCRUM-193/205: Formalización de Garantías + Registro CYF --------

    /**
     * Crea un crédito 'aprobada_garantias' cuyo cliente_id apunta a un User
     * de portal real (no al registro Cliente de crearCredito(), que no es
     * autenticable) — necesario para simular la carga real del cliente vía
     * POST /api/uploads.
     */
    private function crearCreditoConClientePortal(string $sufijo): array
    {
        $clienteUser = User::create([
            'name' => 'Cliente Portal ' . $sufijo,
            'email' => "cliente.portal.{$sufijo}@test.com",
            'password' => bcrypt('password'),
            'numero_documento' => 'cliente_portal_' . $sufijo,
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['cliente'],
        ]);

        $solicitud = $this->crearSolicitud($sufijo);
        $credito = CreditoOrdinario::create([
            'numero_solicitud' => 'CO-GC-FG-' . $sufijo,
            'cliente_id' => $clienteUser->id,
            'solicitud_credito_id' => $solicitud->id,
            'monto' => 30000000,
            'plazo_meses' => 12,
            'estado' => 'aprobada_garantias',
            'resultado_origen' => 'comite_aprobado',
            'documentos' => [],
        ]);

        return [$credito, $clienteUser];
    }

    private function notificarAprobadaGarantias(CreditoOrdinario $credito, array $headers): void
    {
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Aprobación de garantías',
            'mensaje' => 'Debe formalizar las garantías.',
            'preset_id' => $this->preset->id,
        ], $headers)->assertStatus(200);
    }

    public function test_cliente_completa_garantias_avanza_a_pendiente_formalizacion_garantias(): void
    {
        [$credito, $clienteUser] = $this->crearCreditoConClientePortal('1');
        $headers = ['X-Active-Role' => 'coordinador_comercial'];

        Passport::actingAs($this->coordinador);
        $this->notificarAprobadaGarantias($credito, $headers);

        $item = DocumentRequestItem::whereHas('request', function ($q) use ($credito) {
            $q->where('solicitud_credito_id', $credito->solicitud_credito_id);
        })->firstOrFail();

        Passport::actingAs($clienteUser);
        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->create('pagare.pdf', 100, 'application/pdf'),
            'active_role' => 'cliente',
            'document_request_item_id' => $item->id,
        ])->assertStatus(200);

        $credito->refresh();
        $this->assertSame('pendiente_formalizacion_garantias', $credito->estado);
        $this->assertFalse($credito->solicitud_gestionada);
        $this->assertNull($credito->fecha_gestion);

        $item->refresh();
        $this->assertSame('subido', $item->estado);
    }

    /** SCRUM-280: al completar el cargue, Operativo (no asignación por
     * crédito) debe recibir la notificación — todos los activos con el rol. */
    public function test_cliente_completa_garantias_notifica_a_operativo(): void
    {
        [$credito, $clienteUser] = $this->crearCreditoConClientePortal('1b');
        $headers = ['X-Active-Role' => 'coordinador_comercial'];

        Passport::actingAs($this->coordinador);
        $this->notificarAprobadaGarantias($credito, $headers);

        $item = DocumentRequestItem::whereHas('request', function ($q) use ($credito) {
            $q->where('solicitud_credito_id', $credito->solicitud_credito_id);
        })->firstOrFail();

        Passport::actingAs($clienteUser);
        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->create('pagare.pdf', 100, 'application/pdf'),
            'active_role' => 'cliente',
            'document_request_item_id' => $item->id,
        ])->assertStatus(200);

        Mail::assertSent(FormalizacionGarantiasPendienteOperativoMail::class, function ($mail) {
            return $mail->hasTo($this->operativo->email);
        });
    }

    /**
     * SCRUM-293: reproduce el escenario exacto del ticket (crédito
     * CO-2026-KUIS25) — el cliente carga el documento de garantías directo
     * desde Crédito Ordinario (POST /api/creditos/{id}/transition, botón
     * "Subir" de Etapa 4/5, habilitado para el rol cliente por SCRUM-292),
     * NO desde "Solicitud de Documentos" (POST /api/uploads, que sí
     * disparaba correctamente el avance — ver los 2 tests de arriba). Antes
     * del fix, GarantiasFormalizacionService nunca se invocaba desde este
     * segundo camino: el crédito quedaba trabado en 'aprobada_garantias'
     * para siempre, sin notificar a Operativo ni aparecer en su bandeja.
     */
    public function test_subir_garantias_desde_credito_ordinario_avanza_y_notifica_operativo(): void
    {
        [$credito, $clienteUser] = $this->crearCreditoConClientePortal('293');
        $headers = ['X-Active-Role' => 'coordinador_comercial'];

        Passport::actingAs($this->coordinador);
        $this->notificarAprobadaGarantias($credito, $headers);

        $item = DocumentRequestItem::whereHas('request', function ($q) use ($credito) {
            $q->where('solicitud_credito_id', $credito->solicitud_credito_id);
        })->firstOrFail();

        Passport::actingAs($clienteUser);
        $this->postJson("/api/creditos/{$credito->id}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'req_item_' . $item->id,
            'archivos' => [UploadedFile::fake()->create('pagare.pdf', 100, 'application/pdf')],
        ], ['X-Active-Role' => 'cliente'])->assertStatus(200);

        $credito->refresh();
        $this->assertSame('pendiente_formalizacion_garantias', $credito->estado);
        $this->assertFalse($credito->solicitud_gestionada);
        $this->assertNull($credito->fecha_gestion);

        $item->refresh();
        $this->assertSame('subido', $item->estado);

        Mail::assertSent(FormalizacionGarantiasPendienteOperativoMail::class, function ($mail) {
            return $mail->hasTo($this->operativo->email);
        });
    }

    /**
     * Trae el crédito hasta 'pendiente_formalizacion_garantias' (mismo
     * camino que el test de arriba) para los tests de guardarFormalizacionGarantias().
     */
    private function credioPendienteFormalizacion(string $sufijo): array
    {
        [$credito, $clienteUser] = $this->crearCreditoConClientePortal($sufijo);
        $headers = ['X-Active-Role' => 'coordinador_comercial'];

        Passport::actingAs($this->coordinador);
        $this->notificarAprobadaGarantias($credito, $headers);

        $item = DocumentRequestItem::whereHas('request', function ($q) use ($credito) {
            $q->where('solicitud_credito_id', $credito->solicitud_credito_id);
        })->firstOrFail();

        Passport::actingAs($clienteUser);
        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->create('pagare.pdf', 100, 'application/pdf'),
            'active_role' => 'cliente',
            'document_request_item_id' => $item->id,
        ])->assertStatus(200);

        $credito->refresh();

        return [$credito, $item];
    }

    /**
     * Regresión SCRUM-229: el archivo subido por el cliente desde la
     * pantalla de "Solicitud de Documentos" (POST /api/uploads con
     * document_request_item_id, habilitada por
     * GestionCreditoController::crearSolicitudDocumentos()) debe verse
     * también desde Crédito Ordinario Etapa 4 — antes solo se veía el
     * preset (nombres de items), pero no el archivo cargado, porque
     * GET /api/creditos/{id} no traía la relación item.upload.
     */
    public function test_documento_subido_via_solicitud_documentos_visible_en_credito_ordinario(): void
    {
        [$credito, $item] = $this->credioPendienteFormalizacion('2');

        Passport::actingAs($credito->cliente);
        $response = $this->getJson("/api/creditos/{$credito->id}", ['X-Active-Role' => 'cliente']);

        $response->assertStatus(200);
        $itemJson = collect($response->json('solicitud_credito.garantias_document_request.items'))
            ->firstWhere('id', $item->id);
        $this->assertNotNull($itemJson['upload'] ?? null, 'El item de garantías debe traer el upload real.');
        $this->assertSame('pagare.pdf', $itemJson['upload']['original_name']);
    }

    public function test_guardar_formalizacion_garantias_falla_si_no_esta_pendiente(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', 'fg-guard');

        Passport::actingAs($this->operativo);
        $this->postJson("/api/gestion-creditos/{$credito->id}/formalizacion-garantias", [
            'items' => [],
        ], ['X-Active-Role' => 'operativo'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Esta solicitud no está pendiente de Formalización de Garantías.']);
    }

    public function test_guardar_formalizacion_garantias_falla_para_coordinador_comercial(): void
    {
        [$credito, $item] = $this->credioPendienteFormalizacion('2b');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/formalizacion-garantias", [
            'items' => [['item_id' => $item->id, 'validacion' => 'aprobada']],
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(403);
    }

    public function test_guardar_formalizacion_garantias_no_aprobada_sin_observaciones_falla(): void
    {
        [$credito, $item] = $this->credioPendienteFormalizacion('2');

        Passport::actingAs($this->operativo);
        $this->postJson("/api/gestion-creditos/{$credito->id}/formalizacion-garantias", [
            'items' => [['item_id' => $item->id, 'validacion' => 'no_aprobada']],
        ], ['X-Active-Role' => 'operativo'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Las garantías no aprobadas requieren observaciones.']);
    }

    public function test_guardar_formalizacion_garantias_con_ajuste_vuelve_a_aprobada_garantias(): void
    {
        [$credito, $item] = $this->credioPendienteFormalizacion('3');

        Passport::actingAs($this->operativo);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/formalizacion-garantias", [
            'items' => [[
                'item_id' => $item->id,
                'validacion' => 'no_aprobada',
                'observaciones' => 'Falta firma en el formulario.',
            ]],
        ], ['X-Active-Role' => 'operativo']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'aprobada_garantias');

        $credito->refresh();
        $this->assertSame('aprobada_garantias', $credito->estado);
        $this->assertFalse($credito->solicitud_gestionada);

        $item->refresh();
        $this->assertSame('rechazado', $item->estado);
        $this->assertSame('Falta firma en el formulario.', $item->observaciones);

        Mail::assertSent(FormalizacionGarantiasResultadoMail::class, function ($mail) {
            return $mail->requiereAjustes === true && $mail->urlPortalCliente !== null;
        });

        // SCRUM-284 REGLA CRÍTICA: con ajustes NO se notifica al Coordinador.
        Mail::assertNotSent(FormalizacionGarantiasCoordinadorMail::class);
    }

    public function test_guardar_formalizacion_garantias_todas_aprobadas_pasa_a_pendiente_registro_cyf(): void
    {
        [$credito, $item] = $this->credioPendienteFormalizacion('4');

        Passport::actingAs($this->operativo);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/formalizacion-garantias", [
            'items' => [['item_id' => $item->id, 'validacion' => 'aprobada']],
        ], ['X-Active-Role' => 'operativo']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'pendiente_registro_cyf');

        $credito->refresh();
        $this->assertSame('pendiente_registro_cyf', $credito->estado);
        $this->assertFalse($credito->solicitud_gestionada);

        $item->refresh();
        $this->assertSame('aprobado', $item->estado);

        Mail::assertSent(FormalizacionGarantiasResultadoMail::class, function ($mail) {
            return $mail->requiereAjustes === false && $mail->urlPortalCliente === null;
        });

        // SCRUM-284: todas aprobadas → avisa al Coordinador Comercial
        // ASIGNADO (solicitudCredito->usuarioRegistra, acá $this->admin por
        // crearSolicitud()) — no a todos los usuarios con rol
        // coordinador_comercial ($this->coordinador no debe recibirlo).
        Mail::assertSent(FormalizacionGarantiasCoordinadorMail::class, function ($mail) {
            return $mail->hasTo($this->admin->email);
        });
        Mail::assertNotSent(FormalizacionGarantiasCoordinadorMail::class, function ($mail) {
            return $mail->hasTo($this->coordinador->email);
        });
    }

    private function creditoPendienteRegistroCyf(string $sufijo): CreditoOrdinario
    {
        [$credito, $item] = $this->credioPendienteFormalizacion($sufijo);

        Passport::actingAs($this->operativo);
        $this->postJson("/api/gestion-creditos/{$credito->id}/formalizacion-garantias", [
            'items' => [['item_id' => $item->id, 'validacion' => 'aprobada']],
        ], ['X-Active-Role' => 'operativo'])->assertStatus(200);

        return $credito->fresh();
    }

    public function test_registro_cyf_falla_si_no_esta_pendiente(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', 'cyf-guard');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/registro-cyf", [
            'fecha_registro_cyf' => '2026-08-17',
            'radicado_cyf' => 'RAD-001',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Esta solicitud no está pendiente de Registro de Crédito en CYF.']);
    }

    public function test_registro_cyf_valida_campos_requeridos(): void
    {
        $credito = $this->creditoPendienteRegistroCyf('5');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/registro-cyf", [], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Ingrese la fecha del registro del crédito en CYF.']);
    }

    public function test_registro_cyf_guarda_datos_y_pasa_a_aprobacion_registro_cyf(): void
    {
        $credito = $this->creditoPendienteRegistroCyf('6');

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/registro-cyf", [
            'fecha_registro_cyf' => '2026-08-17',
            'radicado_cyf' => 'RAD-2026-001',
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'aprobacion_registro_cyf');

        $credito->refresh();
        $this->assertSame('aprobacion_registro_cyf', $credito->estado);
        // SCRUM-211: queda en false, no true — 'aprobacion_registro_cyf' ya
        // tiene tarjeta/pantalla propia (Gerente) y debe aparecer pendiente
        // de gestión en vez de darse por gestionado acá.
        $this->assertFalse($credito->solicitud_gestionada);
        $this->assertSame('2026-08-17', $credito->fecha_registro_cyf->toDateString());
        $this->assertSame('RAD-2026-001', $credito->radicado_cyf);
        // Reutiliza el gate legacy de Gerencia (ver docblock de registroCyf()).
        $this->assertSame('RAD-2026-001', $credito->documentos_raw['registro_cyf'] ?? null);
    }

    /** SCRUM-288: al guardar fecha + radicado, Gerencia (no asignación por
     * crédito) debe recibir la notificación — todos los activos con el rol. */
    public function test_registro_cyf_notifica_a_gerente(): void
    {
        $credito = $this->creditoPendienteRegistroCyf('6b');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/registro-cyf", [
            'fecha_registro_cyf' => '2026-08-17',
            'radicado_cyf' => 'RAD-2026-002',
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(200);

        Mail::assertSent(RegistroCyfPendienteAprobacionMail::class, function ($mail) {
            return $mail->hasTo($this->gerente->email) && $mail->nombreCoordinador === $this->coordinador->name;
        });
    }

    /** SCRUM-288 (rebote, comentario de Juan): el correo debe incluir tipo de
     * crédito, tipo de documento y número de documento del cliente. */
    public function test_registro_cyf_notifica_a_gerente_incluye_tipo_credito_y_documento_cliente(): void
    {
        $credito = $this->creditoPendienteRegistroCyf('6c');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/registro-cyf", [
            'fecha_registro_cyf' => '2026-08-17',
            'radicado_cyf' => 'RAD-2026-003',
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(200);

        Mail::assertSent(RegistroCyfPendienteAprobacionMail::class, function ($mail) {
            return $mail->tipoCredito === 'Crédito Ordinario'
                && $mail->tipoDocumento === 'Cédula'
                && $mail->numeroDocumento === '90807060';
        });
    }

    // ---- SCRUM-211: Aprobación Registro de Crédito en CYF ----------------

    private function creditoPendienteAprobacionRegistroCyf(string $sufijo): CreditoOrdinario
    {
        $credito = $this->creditoPendienteRegistroCyf($sufijo);

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/registro-cyf", [
            'fecha_registro_cyf' => '2026-08-17',
            'radicado_cyf' => 'RAD-' . $sufijo,
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(200);

        return $credito->fresh();
    }

    public function test_aprobacion_registro_cyf_falla_si_no_esta_pendiente(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', 'arc-guard');

        Passport::actingAs($this->gerente);
        $this->postJson("/api/gestion-creditos/{$credito->id}/aprobacion-registro-cyf", [
            'decision' => 'aprobar',
        ], ['X-Active-Role' => 'gerente'])->assertStatus(422);
    }

    public function test_aprobacion_registro_cyf_solo_gerente_o_superadmin(): void
    {
        $credito = $this->creditoPendienteAprobacionRegistroCyf('arc-rol');

        Passport::actingAs($this->operativo);
        $this->postJson("/api/gestion-creditos/{$credito->id}/aprobacion-registro-cyf", [
            'decision' => 'aprobar',
        ], ['X-Active-Role' => 'operativo'])->assertStatus(403);
    }

    public function test_aprobacion_registro_cyf_rechazar_sin_observaciones_falla(): void
    {
        $credito = $this->creditoPendienteAprobacionRegistroCyf('arc-obs');

        Passport::actingAs($this->gerente);
        $this->postJson("/api/gestion-creditos/{$credito->id}/aprobacion-registro-cyf", [
            'decision' => 'rechazar',
        ], ['X-Active-Role' => 'gerente'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Ingrese las observaciones del rechazo.']);
    }

    public function test_aprobacion_registro_cyf_aprobar_pasa_a_desembolso_ingreso_y_notifica_operativo(): void
    {
        $credito = $this->creditoPendienteAprobacionRegistroCyf('arc-ok');

        Passport::actingAs($this->gerente);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/aprobacion-registro-cyf", [
            'decision' => 'aprobar',
        ], ['X-Active-Role' => 'gerente']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'desembolso_ingreso');

        $credito->refresh();
        $this->assertSame('desembolso_ingreso', $credito->estado);
        $this->assertFalse($credito->solicitud_gestionada);

        Mail::assertSent(RegistroCyfAprobadoMail::class, function ($mail) use ($credito) {
            return $mail->credito->id === $credito->id && str_contains($mail->urlIngreso, '/login?returnTo=');
        });
    }

    // ---- SCRUM-294: NTF-02/NTF-03 a Coordinador Comercial ------------------

    public function test_aprobacion_registro_cyf_aprobar_notifica_tambien_a_coordinador_comercial(): void
    {
        $credito = $this->creditoPendienteAprobacionRegistroCyf('arc-ntf02');

        Passport::actingAs($this->gerente);
        $this->postJson("/api/gestion-creditos/{$credito->id}/aprobacion-registro-cyf", [
            'decision' => 'aprobar',
        ], ['X-Active-Role' => 'gerente'])->assertStatus(200);

        // NTF-01 y NTF-02 son independientes entre sí (§4.1 del ticket) —
        // ambas deben salir por la misma decisión de aprobación.
        // Rebote 2026-08-31 (comentario de Juan): ambos correos deben traer
        // cliente, fecha de registro CYF y radicado CYF; el de Coordinador
        // además la decisión de Gerencia (§9 del ticket).
        Mail::assertSent(RegistroCyfAprobadoMail::class, function ($mail) use ($credito) {
            return $mail->credito->id === $credito->id
                && $mail->nombreCliente === 'Gestion Test'
                && $mail->fechaRegistroCyf->toDateString() === '2026-08-17'
                && $mail->radicadoCyf === $credito->radicado_cyf
                && $mail->nombreGerente === $this->gerente->name;
        });
        Mail::assertSent(RegistroCyfAprobadoCoordinadorMail::class, function ($mail) use ($credito) {
            return $mail->credito->id === $credito->id
                && $mail->nombreCliente === 'Gestion Test'
                && $mail->fechaRegistroCyf->toDateString() === '2026-08-17'
                && $mail->radicadoCyf === $credito->radicado_cyf
                && $mail->nombreGerente === $this->gerente->name
                && $mail->observaciones === null;
        });
        Mail::assertNotSent(RegistroCyfRechazadoCoordinadorMail::class);

        $this->assertDatabaseHas('activity_logs', [
            'accion' => 'notificacion_rol_enviada',
            'entidad_type' => CreditoOrdinario::class,
            'entidad_id' => $credito->id,
        ]);
    }

    public function test_aprobacion_registro_cyf_rechazar_limpia_datos_y_vuelve_a_pendiente_registro_cyf(): void
    {
        $credito = $this->creditoPendienteAprobacionRegistroCyf('arc-rej');
        $radicadoOriginal = $credito->radicado_cyf;

        Passport::actingAs($this->gerente);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/aprobacion-registro-cyf", [
            'decision' => 'rechazar',
            'observaciones' => 'Radicado ilegible, cargar de nuevo.',
        ], ['X-Active-Role' => 'gerente']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'pendiente_registro_cyf');

        $credito->refresh();
        $this->assertSame('pendiente_registro_cyf', $credito->estado);
        $this->assertNull($credito->fecha_registro_cyf);
        $this->assertNull($credito->radicado_cyf);
        $this->assertFalse($credito->solicitud_gestionada);
        // §4.2 del ticket: rechazo no envía NTF-01 (Operativo) ni el aviso
        // de continuidad (NTF-02) — únicamente NTF-03 a Coordinador Comercial.
        Mail::assertNotSent(RegistroCyfAprobadoMail::class);
        Mail::assertNotSent(RegistroCyfAprobadoCoordinadorMail::class);
        Mail::assertSent(RegistroCyfRechazadoCoordinadorMail::class, function ($mail) use ($credito, $radicadoOriginal) {
            // urlIngresoSistema() urlencode() la ruta de retorno — no queda
            // literal el '/' de '/registro-cyf' en la URL final.
            // Rebote 2026-08-31 (comentario de Juan): el correo debe seguir
            // mostrando la fecha/radicado que se rechazaron, aunque el
            // rechazo ya los haya puesto en null sobre $credito antes de
            // notificar — de ahí que se comparen contra el valor capturado
            // ANTES del rechazo, no contra $credito->radicado_cyf (ya null).
            return $mail->credito->id === $credito->id
                && $mail->nombreCliente === 'Gestion Test'
                && $mail->fechaRegistroCyf->toDateString() === '2026-08-17'
                && $mail->radicadoCyf === $radicadoOriginal
                && $mail->nombreGerente === $this->gerente->name
                && $mail->observaciones === 'Radicado ilegible, cargar de nuevo.'
                && str_contains($mail->urlIngreso, 'registro-cyf');
        });
    }

    // ---- SCRUM-215: Registro de Operación Desembolso en CYF ---------------

    private function creditoPendienteDesembolsoIngreso(string $sufijo): CreditoOrdinario
    {
        $credito = $this->creditoPendienteAprobacionRegistroCyf($sufijo);

        Passport::actingAs($this->gerente);
        $this->postJson("/api/gestion-creditos/{$credito->id}/aprobacion-registro-cyf", [
            'decision' => 'aprobar',
        ], ['X-Active-Role' => 'gerente'])->assertStatus(200);

        return $credito->fresh();
    }

    public function test_desembolso_ingreso_falla_si_no_esta_pendiente(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', 'di-guard');

        Passport::actingAs($this->operativo);
        $this->postJson("/api/gestion-creditos/{$credito->id}/desembolso-ingreso", [
            'document_preset_id' => $this->presetDesembolso->id,
        ], ['X-Active-Role' => 'operativo'])->assertStatus(422);
    }

    public function test_desembolso_ingreso_solo_operativo_o_superadmin(): void
    {
        $credito = $this->creditoPendienteDesembolsoIngreso('di-rol');

        Passport::actingAs($this->gerente);
        $this->postJson("/api/gestion-creditos/{$credito->id}/desembolso-ingreso", [
            'document_preset_id' => $this->presetDesembolso->id,
        ], ['X-Active-Role' => 'gerente'])->assertStatus(403);
    }

    public function test_desembolso_ingreso_exige_todos_los_documentos_del_preset(): void
    {
        $credito = $this->creditoPendienteDesembolsoIngreso('di-falta');

        Passport::actingAs($this->operativo);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/desembolso-ingreso", [
            'document_preset_id' => $this->presetDesembolso->id,
            'documentos' => [$this->reqPagare->id => UploadedFile::fake()->create('pagare.pdf', 100, 'application/pdf')],
        ], ['X-Active-Role' => 'operativo']);

        $response->assertStatus(422);
        $this->assertStringContainsString('Comprobante de Egreso', $response->json('message'));
    }

    public function test_desembolso_ingreso_guarda_documentos_y_notifica_gerente(): void
    {
        $credito = $this->creditoPendienteDesembolsoIngreso('di-ok');

        Passport::actingAs($this->operativo);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/desembolso-ingreso", [
            'document_preset_id' => $this->presetDesembolso->id,
            'observaciones' => 'Todo en orden.',
            'documentos' => [
                $this->reqPagare->id => UploadedFile::fake()->create('pagare.pdf', 100, 'application/pdf'),
                $this->reqComprobante->id => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
            ],
        ], ['X-Active-Role' => 'operativo']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'desembolso_aprobacion');

        $credito->refresh();
        $this->assertSame('desembolso_aprobacion', $credito->estado);
        $this->assertFalse($credito->solicitud_gestionada);
        $this->assertSame($this->presetDesembolso->id, $credito->documentos_desembolso['preset_id']);
        $this->assertCount(2, $credito->documentos_desembolso['documentos']);
        $this->assertNotEmpty($credito->documentos_raw['desembolso_egreso']);

        // Rebote 2026-08-31 (comentario de Juan): el asunto y el cuerpo no
        // coincidían con §9 del ticket (SCRUM-299) — faltaban cliente,
        // registrado por y fecha/hora del registro.
        Mail::assertSent(DesembolsoRegistradoMail::class, function ($mail) use ($credito) {
            return $mail->credito->id === $credito->id
                && $mail->nombreCliente === 'Gestion Test'
                && $mail->usuarioOperativo === $this->operativo->name
                && str_contains($mail->envelope()->subject, 'pendiente de validación y aprobación');
        });
    }

    public function test_desembolso_ingreso_registra_actividad_de_la_notificacion_a_gerente(): void
    {
        $credito = $this->creditoPendienteDesembolsoIngreso('di-log');

        Passport::actingAs($this->operativo);
        $this->postJson("/api/gestion-creditos/{$credito->id}/desembolso-ingreso", [
            'document_preset_id' => $this->presetDesembolso->id,
            'documentos' => [
                $this->reqPagare->id => UploadedFile::fake()->create('pagare.pdf', 100, 'application/pdf'),
                $this->reqComprobante->id => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
            ],
        ], ['X-Active-Role' => 'operativo'])->assertStatus(200);

        // creditoPendienteDesembolsoIngreso() ya generó su propia entrada
        // 'notificacion_rol_enviada' (NTF-01/02 de aprobacionRegistroCyf())
        // para este mismo crédito — se filtra por tipo_notificacion en vez
        // de tomar la primera fila.
        $log = ActivityLog::where('accion', 'notificacion_rol_enviada')
            ->where('entidad_id', $credito->id)
            ->get()
            ->firstWhere('metadata.tipo_notificacion', 'desembolso_registrado_gerente');
        $this->assertNotNull($log, 'No se registró la auditoría de la notificación a Gerente.');
        $this->assertSame('gerente', $log->metadata['rol_destino']);
    }

    public function test_desembolso_ingreso_registra_actividad_al_fallar_la_notificacion_a_gerente(): void
    {
        $credito = $this->creditoPendienteDesembolsoIngreso('di-log-fail');

        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \Exception('SMTP no disponible'));

        Passport::actingAs($this->operativo);
        // Un fallo de envío es solo informativo (§"Registro de intentos,
        // resultados, fallas" de SCRUM-299) — no revierte el registro de la
        // operación, que ya quedó guardado.
        $this->postJson("/api/gestion-creditos/{$credito->id}/desembolso-ingreso", [
            'document_preset_id' => $this->presetDesembolso->id,
            'documentos' => [
                $this->reqPagare->id => UploadedFile::fake()->create('pagare.pdf', 100, 'application/pdf'),
                $this->reqComprobante->id => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
            ],
        ], ['X-Active-Role' => 'operativo'])->assertStatus(200);

        $credito->refresh();
        $this->assertSame('desembolso_aprobacion', $credito->estado);

        $this->assertDatabaseHas('activity_logs', [
            'accion' => 'notificacion_rol_fallida',
            'entidad_type' => CreditoOrdinario::class,
            'entidad_id' => $credito->id,
        ]);
    }

    // ---- SCRUM-219: Aprobación de Registro de Operación de Desembolso ----

    private function creditoPendienteDesembolsoAprobacion(string $sufijo): CreditoOrdinario
    {
        $credito = $this->creditoPendienteDesembolsoIngreso($sufijo);

        Passport::actingAs($this->operativo);
        $this->postJson("/api/gestion-creditos/{$credito->id}/desembolso-ingreso", [
            'document_preset_id' => $this->presetDesembolso->id,
            'documentos' => [
                $this->reqPagare->id => UploadedFile::fake()->create('pagare.pdf', 100, 'application/pdf'),
                $this->reqComprobante->id => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
            ],
        ], ['X-Active-Role' => 'operativo'])->assertStatus(200);

        return $credito->fresh();
    }

    public function test_desembolso_aprobacion_falla_si_no_esta_pendiente(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', 'da-guard');

        Passport::actingAs($this->gerente);
        $this->postJson("/api/gestion-creditos/{$credito->id}/desembolso-aprobacion", [
            'decision' => 'aprobar',
        ], ['X-Active-Role' => 'gerente'])->assertStatus(422);
    }

    public function test_desembolso_aprobacion_aprobar_pasa_a_ejecucion_transferencia_y_notifica_tesoreria(): void
    {
        $credito = $this->creditoPendienteDesembolsoAprobacion('da-ok');

        Passport::actingAs($this->gerente);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/desembolso-aprobacion", [
            'decision' => 'aprobar',
        ], ['X-Active-Role' => 'gerente']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'ejecucion_transferencia');

        $credito->refresh();
        $this->assertSame('ejecucion_transferencia', $credito->estado);
        // SCRUM-224: antes quedaba en true porque 'ejecucion_transferencia'
        // todavía no tenía tarjeta propia — ahora sí (transferenciaBancaria())
        // y necesita aparecer como pendiente de gestión para Tesorería.
        $this->assertFalse($credito->solicitud_gestionada);

        // Rebote 2026-08-31 (comentario de Juan): ambos correos deben traer
        // cliente, tipo de crédito, quién decidió y fecha/hora de la decisión.
        Mail::assertSent(DesembolsoAprobadoTesoreriaMail::class, function ($mail) use ($credito) {
            return $mail->credito->id === $credito->id
                && $mail->nombreCliente === 'Gestion Test'
                && $mail->tipoCredito === 'Crédito Ordinario'
                && $mail->nombreGerente === $this->gerente->name;
        });

        // SCRUM-303: la aprobación también notifica a Operativo, de forma
        // informativa — la solicitud sigue bajo gestión de Tesorería.
        Mail::assertSent(DesembolsoAprobadoOperativoMail::class, function ($mail) use ($credito) {
            return $mail->credito->id === $credito->id
                && $mail->nombreCliente === 'Gestion Test'
                && $mail->tipoCredito === 'Crédito Ordinario'
                && $mail->nombreGerente === $this->gerente->name;
        });
    }

    public function test_desembolso_aprobacion_rechazar_sin_observaciones_falla(): void
    {
        $credito = $this->creditoPendienteDesembolsoAprobacion('da-rej-sin-obs');

        Passport::actingAs($this->gerente);
        $this->postJson("/api/gestion-creditos/{$credito->id}/desembolso-aprobacion", [
            'decision' => 'rechazar',
        ], ['X-Active-Role' => 'gerente'])->assertStatus(422);

        Mail::assertNotSent(DesembolsoRechazadoOperativoMail::class);
    }

    public function test_desembolso_aprobacion_rechazar_vuelve_a_desembolso_ingreso_y_notifica_operativo(): void
    {
        $credito = $this->creditoPendienteDesembolsoAprobacion('da-rej');

        Passport::actingAs($this->gerente);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/desembolso-aprobacion", [
            'decision' => 'rechazar',
            'observaciones' => 'Falta el comprobante correcto.',
        ], ['X-Active-Role' => 'gerente']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'desembolso_ingreso');

        $credito->refresh();
        $this->assertSame('desembolso_ingreso', $credito->estado);
        $this->assertFalse($credito->solicitud_gestionada);

        // Rebote 2026-08-31 (comentario de Juan): mismo resumen que en la
        // aprobación (cliente, tipo de crédito, quién decidió, fecha/hora).
        Mail::assertSent(DesembolsoRechazadoOperativoMail::class, function ($mail) use ($credito) {
            return $mail->credito->id === $credito->id
                && $mail->observaciones === 'Falta el comprobante correcto.'
                && $mail->nombreCliente === 'Gestion Test'
                && $mail->tipoCredito === 'Crédito Ordinario'
                && $mail->nombreGerente === $this->gerente->name;
        });
    }

    private function creditoPendienteTransferenciaBancaria(string $sufijo): CreditoOrdinario
    {
        $credito = $this->creditoPendienteDesembolsoAprobacion($sufijo);

        Passport::actingAs($this->gerente);
        $this->postJson("/api/gestion-creditos/{$credito->id}/desembolso-aprobacion", [
            'decision' => 'aprobar',
        ], ['X-Active-Role' => 'gerente'])->assertStatus(200);

        return $credito->fresh();
    }

    private function payloadTransferenciaValido(): array
    {
        return [
            'titular_cuenta' => 'Gestion Test',
            'tipo_documento_titular_id' => $this->docCC->id,
            'numero_documento_titular' => '90807060',
            'entidad_bancaria_id' => $this->entidadBancaria->id,
            'tipo_cuenta' => 'ahorros',
            'numero_cuenta' => '1234567890',
            'numero_cuenta_confirmacion' => '1234567890',
            'moneda_cuenta' => 'COP',
            'correo_notificacion_pago' => 'pago.cliente@test.com',
            'certificado_bancario' => UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf'),
            'fecha_transferencia' => now()->format('Y-m-d'),
            'hora_transferencia' => '10:30',
            'valor_transaccion' => '30000000',
            'valor_transaccion_confirmacion' => '30000000',
            'numero_transaccion' => 'TRX-001',
            'comprobante_transferencia' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
            'declaracion_validacion_bancaria' => '1',
        ];
    }

    // ---- SCRUM-224: Registro de Transferencia Bancaria (Tesorería) -------

    public function test_transferencia_bancaria_falla_si_no_esta_pendiente(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', 'tb-guard');

        Passport::actingAs($this->tesoreria);
        $this->postJson("/api/gestion-creditos/{$credito->id}/transferencia-bancaria", $this->payloadTransferenciaValido(), [
            'X-Active-Role' => 'tesoreria',
        ])->assertStatus(422);
    }

    public function test_transferencia_bancaria_solo_tesoreria_o_superadmin(): void
    {
        $credito = $this->creditoPendienteTransferenciaBancaria('tb-rol');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/transferencia-bancaria", $this->payloadTransferenciaValido(), [
            'X-Active-Role' => 'coordinador_comercial',
        ])->assertStatus(403);
    }

    public function test_transferencia_bancaria_numero_cuenta_y_confirmacion_deben_coincidir(): void
    {
        $credito = $this->creditoPendienteTransferenciaBancaria('tb-cuenta');

        Passport::actingAs($this->tesoreria);
        $payload = $this->payloadTransferenciaValido();
        $payload['numero_cuenta_confirmacion'] = '0000000000';

        $this->postJson("/api/gestion-creditos/{$credito->id}/transferencia-bancaria", $payload, [
            'X-Active-Role' => 'tesoreria',
        ])->assertStatus(422)->assertJsonFragment(['message' => 'El número de cuenta y su confirmación no coinciden.']);
    }

    public function test_transferencia_bancaria_valor_y_confirmacion_deben_coincidir(): void
    {
        $credito = $this->creditoPendienteTransferenciaBancaria('tb-valor');

        Passport::actingAs($this->tesoreria);
        $payload = $this->payloadTransferenciaValido();
        $payload['valor_transaccion_confirmacion'] = '1000';

        $this->postJson("/api/gestion-creditos/{$credito->id}/transferencia-bancaria", $payload, [
            'X-Active-Role' => 'tesoreria',
        ])->assertStatus(422)->assertJsonFragment(['message' => 'El valor de la transacción y su confirmación no coinciden.']);
    }

    public function test_transferencia_bancaria_valor_debe_igualar_el_monto_aprobado(): void
    {
        $credito = $this->creditoPendienteTransferenciaBancaria('tb-parcial');

        Passport::actingAs($this->tesoreria);
        $payload = $this->payloadTransferenciaValido();
        $payload['valor_transaccion'] = '1000';
        $payload['valor_transaccion_confirmacion'] = '1000';

        $this->postJson("/api/gestion-creditos/{$credito->id}/transferencia-bancaria", $payload, [
            'X-Active-Role' => 'tesoreria',
        ])->assertStatus(422)->assertJsonFragment(['message' => 'El valor de la transacción debe ser igual al valor aprobado para desembolso.']);
    }

    public function test_transferencia_bancaria_numero_transaccion_duplicado_falla(): void
    {
        $otro = $this->crearCredito('completado', null, 'tb-dup-otro');
        $otro->update(['numero_transaccion_bancaria' => 'TRX-001']);

        $credito = $this->creditoPendienteTransferenciaBancaria('tb-dup');

        Passport::actingAs($this->tesoreria);
        $this->postJson("/api/gestion-creditos/{$credito->id}/transferencia-bancaria", $this->payloadTransferenciaValido(), [
            'X-Active-Role' => 'tesoreria',
        ])->assertStatus(422)->assertJsonFragment(['message' => 'El número de la transacción ya se encuentra registrado.']);
    }

    public function test_transferencia_bancaria_registra_completa_credito_y_notifica(): void
    {
        $credito = $this->creditoPendienteTransferenciaBancaria('tb-ok');

        Passport::actingAs($this->tesoreria);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/transferencia-bancaria", $this->payloadTransferenciaValido(), [
            'X-Active-Role' => 'tesoreria',
        ]);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'completado');

        $credito->refresh();
        $this->assertSame('completado', $credito->estado);
        $this->assertTrue($credito->solicitud_gestionada);
        $this->assertSame('TRX-001', $credito->numero_transaccion_bancaria);
        $this->assertSame('******7890', $credito->transferencia_bancaria['cuenta_enmascarada']);
        $this->assertSame($this->entidadBancaria->nombre, $credito->transferencia_bancaria['entidad_bancaria_nombre']);
        // Legacy compat: gate genérico de CreditoOrdinarioController.
        $this->assertNotEmpty($credito->documentos['comprobante_transferencia']);

        // Rebote 2026-08-31 (comentario de Juan): faltaban tipo de documento
        // del cliente, fecha de solicitud, tipo de crédito y toda la sección
        // de datos bancarios del titular en ambos correos.
        Mail::assertSent(TransferenciaRealizadaClienteMail::class, function ($mail) use ($credito) {
            return $mail->hasTo('pago.cliente@test.com')
                && $mail->credito->id === $credito->id
                && $mail->cuentaEnmascarada === '******7890'
                && $mail->tipoDocumentoCliente === 'Cédula'
                && $mail->tipoCredito === 'Crédito Ordinario'
                && $mail->transferencia['tipo_documento_titular_nombre'] === 'Cédula';
        });
        // SCRUM-307: el comprobante va adjunto en ambas notificaciones, no
        // solo en la del cliente.
        Mail::assertSent(TransferenciaRegistradaInternaMail::class, function ($mail) use ($credito) {
            return $mail->credito->id === $credito->id
                && !empty($mail->attachments())
                && $mail->tipoDocumentoCliente === 'Cédula'
                && $mail->tipoCredito === 'Crédito Ordinario'
                && $mail->transferencia['tipo_documento_titular_nombre'] === 'Cédula';
        });
        Mail::assertSentTimes(TransferenciaRegistradaInternaMail::class, 2); // Gerente + Coordinador Comercial
    }

    // ---- Visibilidad por rol (tarjetas/index) -----------------------------

    public function test_tarjetas_gerente_solo_ve_sus_propias_claves(): void
    {
        $this->crearCredito('aprobada_garantias', 'comite_aprobado', 'vis-1');
        $this->creditoPendienteAprobacionRegistroCyf('vis-2');

        Passport::actingAs($this->gerente);
        $response = $this->getJson('/api/gestion-creditos/tarjetas', ['X-Active-Role' => 'gerente']);

        $response->assertStatus(200)->assertJson(['aprobacion_registro_cyf' => 1]);
        $this->assertArrayNotHasKey('aprobada_garantias', $response->json());
    }

    public function test_operativo_no_puede_ver_detalle_de_credito_fuera_de_su_clave(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', 'vis-3');

        Passport::actingAs($this->operativo);
        $this->getJson("/api/gestion-creditos/{$credito->id}", ['X-Active-Role' => 'operativo'])
            ->assertStatus(404);
    }

    // ---- SCRUM-268: precarga de plantilla, vista previa, auditoría y ------
    // ---- control de duplicados --------------------------------------------

    public function test_show_precarga_plantilla_sugerida_por_escenario(): void
    {
        $credito = $this->crearCredito('rechazado', 'comite_rechazado', 'plt-1');

        Passport::actingAs($this->coordinador);
        $response = $this->getJson("/api/gestion-creditos/{$credito->id}", ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200);
        $plantilla = $response->json('plantilla_sugerida');

        $this->assertStringContainsString($credito->numero_solicitud, $plantilla['asunto']);
        $this->assertStringContainsString($credito->numero_solicitud, $plantilla['mensaje']);
        $this->assertStringContainsString('Comité de Crédito decidió no aprobarla', $plantilla['mensaje']);
    }

    public function test_show_no_precarga_plantilla_si_la_solicitud_ya_fue_gestionada(): void
    {
        $credito = $this->crearCredito('rechazado', 'comite_rechazado', 'plt-2');
        $credito->update(['solicitud_gestionada' => true, 'fecha_gestion' => now()]);

        Passport::actingAs($this->coordinador);
        $response = $this->getJson("/api/gestion-creditos/{$credito->id}", ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200)->assertJsonMissingPath('plantilla_sugerida');
    }

    // ---- SCRUM-307 (rebote 2ª vuelta): correo sugerido para precargar -----
    // ---- el formulario de Transferencia Bancaria --------------------------

    public function test_show_expone_correo_cliente_sugerido_para_precargar_transferencia(): void
    {
        $credito = $this->crearCredito('ejecucion_transferencia', null, 'correo-sug-1');

        Passport::actingAs($this->tesoreria);
        $response = $this->getJson("/api/gestion-creditos/{$credito->id}", ['X-Active-Role' => 'tesoreria']);

        $response->assertStatus(200)
            ->assertJsonPath('correo_cliente_sugerido', 'gestion.cliente@test.com');
    }

    public function test_previsualizar_notificacion_renderiza_saludo_lista_y_boton(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', 'prev-1');

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/notificar/preview", [
            'asunto' => 'Documentación requerida',
            'mensaje' => 'Debe formalizar las garantías.',
            'preset_id' => $this->preset->id,
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringContainsString('Estimado(a) Gestion Test:', $html);
        $this->assertStringContainsString('Pagaré firmado', $html);
        $this->assertStringContainsString('Diligenciar garantías', $html);
        // No debe haberse enviado ni registrado nada — es solo vista previa.
        Mail::assertNothingSent();
        $this->assertDatabaseHas('credito_ordinarios', ['id' => $credito->id, 'solicitud_gestionada' => false]);
    }

    public function test_previsualizar_notificacion_sarlaft_no_incluye_aviso_de_confidencialidad(): void
    {
        // SCRUM-298: el aviso "no incluye coincidencias, hallazgos ni
        // observaciones internas" se eliminó del correo al cliente — ya no
        // debe aparecer en ningún escenario, tampoco en SARLAFT desfavorable.
        $credito = $this->crearCredito('rechazado', 'sarlaft', 'prev-2');

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/notificar/preview", [
            'asunto' => 'Resultado de su solicitud',
            'mensaje' => 'No es posible continuar con el trámite.',
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200);
        $this->assertStringNotContainsString('no incluye coincidencias, hallazgos ni observaciones internas', $response->getContent());
    }

    public function test_notificar_registra_actividad_al_enviar_exitosamente(): void
    {
        $credito = $this->crearCredito('rechazado', 'comite_rechazado', 'log-1');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Decisión del Comité',
            'mensaje' => 'Su solicitud fue rechazada por el Comité.',
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'accion' => 'gestion_credito_notificacion_enviada',
            'entidad_type' => CreditoOrdinario::class,
            'entidad_id' => $credito->id,
            'usuario_id' => $this->coordinador->id,
        ]);
    }

    public function test_notificar_registra_actividad_al_fallar_el_envio(): void
    {
        $credito = $this->crearCredito('rechazado', 'comite_rechazado', 'log-2');

        Mail::shouldReceive('to')->once()->with('gestion.cliente@test.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \Exception('SMTP no disponible'));

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Decisión del Comité',
            'mensaje' => 'Su solicitud fue rechazada por el Comité.',
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(422);

        $this->assertDatabaseHas('activity_logs', [
            'accion' => 'gestion_credito_notificacion_fallida',
            'entidad_type' => CreditoOrdinario::class,
            'entidad_id' => $credito->id,
        ]);

        $log = ActivityLog::where('accion', 'gestion_credito_notificacion_fallida')->first();
        $this->assertSame('SMTP no disponible', $log->metadata['error']);
        $this->assertSame(1, $log->metadata['intento_numero']);
    }

    /**
     * RN-16/CA-16: una segunda llamada sobre la misma solicitud ya
     * gestionada (doble clic, reintento de red) no debe mandar un segundo
     * correo — se corta antes de llegar a Mail::send().
     */
    public function test_notificar_dos_veces_seguidas_no_envia_un_segundo_correo(): void
    {
        $credito = $this->crearCredito('rechazado', 'comite_rechazado', 'dup-1');

        Passport::actingAs($this->coordinador);
        $payload = [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Decisión del Comité',
            'mensaje' => 'Su solicitud fue rechazada por el Comité.',
        ];

        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", $payload, ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200);

        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", $payload, ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Esta solicitud ya fue gestionada por Coordinador Test el ' . $credito->fresh()->fecha_gestion->format('d/m/Y H:i') . '.']);

        Mail::assertSent(GestionCreditoNotificacionMail::class, 1);
    }
}
