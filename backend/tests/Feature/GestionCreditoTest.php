<?php

namespace Tests\Feature;

use App\Mail\GestionCreditoNotificacionMail;
use App\Models\Amortizacion;
use App\Models\ClientUpload;
use App\Models\Cliente;
use App\Models\CreditoOrdinario;
use App\Models\DocumentPreset;
use App\Models\DocumentRequest;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\SolicitudCredito;
use App\Models\TipoCredito;
use App\Models\TipoPersona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
    private $docCC;
    private $tipoOrdinario;
    private $amortizacionMensual;
    private $clienteNatural;
    private $preset;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

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

        $this->preset = DocumentPreset::create(['nombre' => 'Preset Garantías', 'descripcion' => 'Documentos de garantías']);
        $requirement = DocumentRequirement::create(['nombre' => 'Pagaré firmado', 'activo' => true]);
        $this->preset->requirements()->attach([$requirement->id]);
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

    public function test_notificar_aprobada_garantias_transiciona_a_formalizacion_garantias(): void
    {
        $credito = $this->crearCredito('aprobada_garantias', 'comite_aprobado', '1');

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Aprobación de garantías',
            'mensaje' => 'Debe formalizar las garantías.',
            'preset_id' => $this->preset->id,
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200)->assertJsonPath('credito.estado', 'formalizacion_garantias');

        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'formalizacion_garantias',
            'solicitud_gestionada' => true,
        ]);
        $this->assertNotNull($credito->fresh()->fecha_gestion);

        Mail::assertSent(GestionCreditoNotificacionMail::class, function ($mail) use ($credito) {
            return $mail->hasTo('gestion.cliente@test.com')
                && $mail->credito->id === $credito->id
                && $mail->asuntoCorreo === 'Aprobación de garantías';
        });
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

        // 3. Coordinador gestiona: transiciona a formalizacion_garantias.
        $this->postJson("/api/gestion-creditos/{$credito->id}/notificar", [
            'destino' => 'gestion.cliente@test.com',
            'asunto' => 'Aprobación de garantías',
            'mensaje' => 'Debe formalizar las garantías.',
            'preset_id' => $this->preset->id,
        ], $headers)->assertStatus(200)->assertJsonPath('credito.estado', 'formalizacion_garantias');

        // 4. SCRUM-190 (2026-08-12): el crédito NO debe desaparecer de la
        // bandeja al gestionarse — debe seguir visible con
        // solicitud_gestionada=true y su fecha_gestion.
        $bandejaPostGestion = $this->getJson('/api/gestion-creditos', $headers);
        $bandejaPostGestion->assertStatus(200)->assertJsonCount(1)
            ->assertJsonPath('0.solicitud_gestionada', true)
            ->assertJsonPath('0.estado', 'formalizacion_garantias');
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

    public function test_revisar_documento_aprueba_item_y_habilita_garantias_al_completar_todos(): void
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

        $response->assertStatus(200)->assertJsonPath('credito_disponible_garantias', true);

        $item->refresh();
        $this->assertSame('aprobado', $item->estado);
        $upload->refresh();
        $this->assertSame('aprobado', $upload->status);

        $documentRequest->refresh();
        $this->assertSame('completado', $documentRequest->estado);

        $credito->refresh();
        $this->assertSame('aprobada_garantias', $credito->estado);
        $this->assertSame('comite_aprobado', $credito->resultado_origen);
        $this->assertFalse($credito->solicitud_gestionada);

        // El crédito vuelve a aparecer pendiente de gestión en la bandeja de
        // "Aprobada para garantías" (SCRUM-199).
        $this->getJson('/api/gestion-creditos?estado=aprobada_garantias', $headers)
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $credito->id]);
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

        $response->assertStatus(200)->assertJsonPath('credito_disponible_garantias', false);

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
}
