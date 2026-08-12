<?php

namespace Tests\Feature;

use App\Models\Amortizacion;
use App\Models\Cliente;
use App\Models\CreditoOrdinario;
use App\Models\DocumentType;
use App\Models\SolicitudCredito;
use App\Models\TipoCredito;
use App\Models\TipoPersona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ActaComiteTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $coordinador;
    private $gerente;
    private $docCC;
    private $tipoOrdinario;
    private $amortizacionMensual;
    private $clienteNatural;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
        $this->tipoOrdinario = TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
        $this->amortizacionMensual = Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);

        $this->clienteNatural = Cliente::create([
            'tipo_persona_id' => $tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '99887766',
            'identificacion' => '99887766',
            'nombre' => 'Acta Test',
            'nombres' => 'Acta',
            'primer_apellido' => 'Test',
            'correo_electronico' => 'acta.cliente@test.com',
            'telefono' => '3005559988',
            'direccion' => 'Calle Acta 1',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Super Administrador', 'email' => 'admin.ac@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'admin_ac', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['superadmin'],
        ]);

        $this->coordinador = User::create([
            'name' => 'Coordinador Test', 'email' => 'coordinador.ac@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'coord_ac', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['coordinador_comercial'],
        ]);

        $this->gerente = User::create([
            'name' => 'Gerente Test', 'email' => 'gerente.ac@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'ger_ac', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['gerente'],
        ]);
    }

    private function crearCreditoEnComiteEvaluacion(string $sufijo = '1'): CreditoOrdinario
    {
        $solicitud = SolicitudCredito::create([
            'cliente_id' => $this->clienteNatural->id,
            'usuario_registra_id' => $this->admin->id,
            'tipo_credito_id' => $this->tipoOrdinario->id,
            'monto_solicitado' => 30000000,
            'plazo_meses' => 12,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Capital de trabajo',
            'garantia' => 'Pagaré',
            'fuente_pago' => 'Ingresos operacionales',
            'correo_notificacion' => 'acta.cliente@test.com',
            'asunto_notificacion' => 'Documentación',
            'mensaje_notificacion' => 'Adjunta los archivos.',
        ]);

        return CreditoOrdinario::create([
            'numero_solicitud' => 'CO-ACTA-' . $sufijo . '-' . $solicitud->id,
            'cliente_id' => $this->clienteNatural->id,
            'solicitud_credito_id' => $solicitud->id,
            'monto' => 30000000,
            'plazo_meses' => 12,
            'estado' => 'comite_evaluacion',
            'documentos' => [],
        ]);
    }

    public function test_generar_falla_sin_creditos_elegibles(): void
    {
        Passport::actingAs($this->coordinador);
        $this->postJson('/api/actas-comite/generar', [], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'No hay créditos con Análisis Financiero confirmado listos para el Comité todavía.',
            ]);
    }

    /**
     * SCRUM-183 (2026-08-05, decisión de Luis tras hablar con Lorena): se
     * eliminó el paso de Presentación para el Comité + aprobación de
     * Gerencia — la presentación ahora se adjunta directo sobre la
     * solicitud dentro del Acta, no antes de llegar a comite_evaluacion.
     */
    public function test_subir_presentacion_adjunta_pdf_a_la_solicitud(): void
    {
        $credito = $this->crearCreditoEnComiteEvaluacion();

        Passport::actingAs($this->coordinador);
        $response = $this->postJson('/api/actas-comite/generar', [], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(201);
        $acta = \App\Models\ActaComite::find($response->json('id'));
        $solicitud = $acta->solicitudes()->first();
        $this->assertNull($solicitud->presentacion_comite);

        $archivo = \Illuminate\Http\UploadedFile::fake()->create('presentacion.pdf', 500, 'application/pdf');
        $response = $this->postJson(
            "/api/actas-comite/{$acta->id}/solicitudes/{$solicitud->id}/presentacion",
            ['archivo' => $archivo],
            ['X-Active-Role' => 'coordinador_comercial']
        );

        $response->assertStatus(200);
        $this->assertNotNull($response->json('presentacion_comite'));
        $solicitud->refresh();
        $this->assertNotNull($solicitud->presentacion_comite);
    }

    public function test_subir_presentacion_rechaza_archivo_no_pdf(): void
    {
        $credito = $this->crearCreditoEnComiteEvaluacion();

        Passport::actingAs($this->coordinador);
        $response = $this->postJson('/api/actas-comite/generar', [], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(201);
        $acta = \App\Models\ActaComite::find($response->json('id'));
        $solicitud = $acta->solicitudes()->first();

        $archivo = \Illuminate\Http\UploadedFile::fake()->create('presentacion.docx', 500, 'application/msword');
        $this->postJson(
            "/api/actas-comite/{$acta->id}/solicitudes/{$solicitud->id}/presentacion",
            ['archivo' => $archivo],
            ['X-Active-Role' => 'coordinador_comercial']
        )->assertStatus(422)->assertJsonValidationErrors(['archivo']);
    }

    public function test_generar_crea_acta_con_creditos_elegibles(): void
    {
        $credito = $this->crearCreditoEnComiteEvaluacion();

        Passport::actingAs($this->coordinador);
        $response = $this->postJson('/api/actas-comite/generar', [], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(201)
            ->assertJsonPath('estado', 'pendiente')
            ->assertJsonPath('numero', 1)
            ->assertJsonCount(1, 'solicitudes');

        $this->assertDatabaseHas('acta_comite_solicitudes', [
            'credito_ordinario_id' => $credito->id,
            'origen' => 'sistema',
            'cliente_nombre' => 'Acta Test',
        ]);
    }

    /**
     * SCRUM-189 (2026-08-12, punto 1): un crédito que llega a
     * comite_evaluacion DESPUÉS de generada el acta pendiente/borrador debe
     * incorporarse solo, sin que el Coordinador tenga que hacer nada — se
     * verifica tanto al reabrir (show) como al autoguardar (actualizar).
     */
    public function test_sincroniza_creditos_nuevos_al_reabrir_y_al_guardar(): void
    {
        $creditoInicial = $this->crearCreditoEnComiteEvaluacion('1');

        Passport::actingAs($this->coordinador);
        $headers = ['X-Active-Role' => 'coordinador_comercial'];
        $acta = $this->postJson('/api/actas-comite/generar', [], $headers)->assertStatus(201)->json();
        $this->assertCount(1, $acta['solicitudes']);

        // Llega un segundo crédito elegible mientras el acta sigue abierta.
        $creditoNuevo = $this->crearCreditoEnComiteEvaluacion('2');

        // Reabrir (GET) ya debe traerlo.
        $this->getJson("/api/actas-comite/{$acta['id']}", $headers)
            ->assertStatus(200)
            ->assertJsonCount(2, 'solicitudes');

        $this->assertDatabaseHas('acta_comite_solicitudes', [
            'acta_comite_id' => $acta['id'],
            'credito_ordinario_id' => $creditoNuevo->id,
            'origen' => 'sistema',
        ]);

        // Un tercer crédito llega y se confirma también vía autoguardado (PUT).
        $creditoNuevo2 = $this->crearCreditoEnComiteEvaluacion('3');
        $this->putJson("/api/actas-comite/{$acta['id']}", ['lugar' => '<p>Sala B</p>'], $headers)
            ->assertStatus(200)
            ->assertJsonCount(3, 'solicitudes');

        $this->assertDatabaseHas('acta_comite_solicitudes', [
            'acta_comite_id' => $acta['id'],
            'credito_ordinario_id' => $creditoNuevo2->id,
            'origen' => 'sistema',
        ]);

        // No duplica el crédito original en cada sync.
        $this->assertDatabaseCount('acta_comite_solicitudes', 3);
        $this->assertDatabaseHas('acta_comite_solicitudes', ['credito_ordinario_id' => $creditoInicial->id]);
    }

    /**
     * SCRUM-189 (2026-08-12, punto 6/7): con una imagen insertada en un
     * campo rich text, generar el PDF (Previsualizar/Descargar) no debe
     * fallar — antes tampoco fallaba (DomPDF ignora en silencio lo que no
     * puede resolver), pero esto deja como regression guard que el HTML
     * resuelto a ruta local sigue siendo válido para DomPDF.
     */
    public function test_descargar_pdf_con_imagen_inline_no_falla(): void
    {
        $this->crearCreditoEnComiteEvaluacion();
        Passport::actingAs($this->coordinador);
        $headers = ['X-Active-Role' => 'coordinador_comercial'];
        $acta = $this->postJson('/api/actas-comite/generar', [], $headers)->assertStatus(201)->json();

        $imagen = \Illuminate\Http\UploadedFile::fake()->image('evidencia.png', 50, 50);
        $subida = $this->postJson("/api/actas-comite/{$acta['id']}/imagenes", ['imagen' => $imagen], $headers)
            ->assertStatus(200)->json();

        $this->putJson("/api/actas-comite/{$acta['id']}", [
            'lugar' => '<p>Sala de juntas</p><img src="' . $subida['url'] . '">',
        ], $headers)->assertStatus(200);

        $this->getJson("/api/actas-comite/{$acta['id']}/descargar", $headers)
            ->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_generar_rechaza_si_ya_existe_pendiente_o_borrador(): void
    {
        $this->crearCreditoEnComiteEvaluacion();

        Passport::actingAs($this->coordinador);
        $this->postJson('/api/actas-comite/generar', [], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(201);

        $this->crearCreditoEnComiteEvaluacion('2');
        $this->postJson('/api/actas-comite/generar', [], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422);
    }

    public function test_gerente_no_puede_generar_ni_ver(): void
    {
        $this->crearCreditoEnComiteEvaluacion();

        Passport::actingAs($this->gerente);
        $this->postJson('/api/actas-comite/generar', [], ['X-Active-Role' => 'gerente'])->assertStatus(403);
    }

    public function test_registrar_falla_con_informacion_incompleta(): void
    {
        $this->crearCreditoEnComiteEvaluacion();
        Passport::actingAs($this->coordinador);
        $acta = $this->postJson('/api/actas-comite/generar', [], ['X-Active-Role' => 'coordinador_comercial'])->json();

        $this->postJson("/api/actas-comite/{$acta['id']}/registrar", [], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'campos_faltantes']);
    }

    public function test_flujo_completo_hasta_registro(): void
    {
        $this->crearCreditoEnComiteEvaluacion();
        Passport::actingAs($this->coordinador);
        $headers = ['X-Active-Role' => 'coordinador_comercial'];

        $acta = $this->postJson('/api/actas-comite/generar', [], $headers)->json();
        $actaId = $acta['id'];
        $solicitudId = $acta['solicitudes'][0]['id'];

        $this->putJson("/api/actas-comite/{$actaId}", [
            'fecha_reunion' => '2026-08-02',
            'lugar' => '<p>Sala de juntas</p>',
            'hora_inicio' => '09:00',
            'asistentes' => [['nombre' => 'Juan Pérez']],
        ], $headers)->assertStatus(200)->assertJsonPath('estado', 'borrador');

        $this->postJson("/api/actas-comite/{$actaId}/aprobar-orden-dia", [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('orden_dia_aprobado', true);

        $this->putJson("/api/actas-comite/{$actaId}/solicitudes/{$solicitudId}", [
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

        $registro = $this->postJson("/api/actas-comite/{$actaId}/registrar", [], $headers);
        $registro->assertStatus(200)->assertJsonPath('acta.estado', 'aprobada');

        // Bloqueada tras registro: ni editar ni volver a registrar.
        $this->putJson("/api/actas-comite/{$actaId}", ['lugar' => '<p>Otro lugar</p>'], $headers)->assertStatus(422);
        $this->postJson("/api/actas-comite/{$actaId}/registrar", [], $headers)->assertStatus(422);

        $this->getJson("/api/actas-comite/{$actaId}/descargar", $headers)
            ->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_agregar_y_eliminar_solicitud_manual(): void
    {
        $this->crearCreditoEnComiteEvaluacion();
        Passport::actingAs($this->coordinador);
        $headers = ['X-Active-Role' => 'coordinador_comercial'];
        $acta = $this->postJson('/api/actas-comite/generar', [], $headers)->json();

        $manual = $this->postJson("/api/actas-comite/{$acta['id']}/solicitudes", [
            'cliente_nombre' => 'Cliente Manual SAS',
            'tipo_solicitud' => 'Factoring',
            'monto' => 5000000,
        ], $headers);
        $manual->assertStatus(201)->assertJsonPath('origen', 'manual');

        $manualId = $manual->json('id');
        $this->deleteJson("/api/actas-comite/{$acta['id']}/solicitudes/{$manualId}", [], $headers)->assertStatus(200);

        // Las de origen "sistema" no se pueden eliminar.
        $sistemaId = $acta['solicitudes'][0]['id'];
        $this->deleteJson("/api/actas-comite/{$acta['id']}/solicitudes/{$sistemaId}", [], $headers)->assertStatus(422);
    }

    /**
     * SCRUM-190 (2026-08-12, punto 1): con identificación/tipo/amortización
     * sin vincular a catálogos reales, registrar() debe bloquear con
     * campos_faltantes en vez de materializar datos inconsistentes.
     */
    public function test_registrar_bloquea_solicitud_manual_no_vinculada_a_catalogos(): void
    {
        $this->crearCreditoEnComiteEvaluacion();
        Passport::actingAs($this->coordinador);
        $headers = ['X-Active-Role' => 'coordinador_comercial'];
        $acta = $this->postJson('/api/actas-comite/generar', [], $headers)->json();
        $sistemaId = $acta['solicitudes'][0]['id'];

        $manual = $this->postJson("/api/actas-comite/{$acta['id']}/solicitudes", [
            'cliente_nombre' => 'Cliente Fantasma SAS',
            'tipo_solicitud' => 'Un tipo que no existe',
            'monto' => 5000000,
        ], $headers)->json();

        $this->putJson("/api/actas-comite/{$acta['id']}/solicitudes/{$manual['id']}", [
            'estado_decision' => 'aprobado', 'monto_decision' => 5000000,
        ], $headers)->assertStatus(200);
        $this->putJson("/api/actas-comite/{$acta['id']}/solicitudes/{$sistemaId}", [
            'estado_decision' => 'aprobado', 'monto_decision' => 30000000,
        ], $headers)->assertStatus(200);

        $this->putJson("/api/actas-comite/{$acta['id']}", [
            'fecha_reunion' => '2026-08-12', 'lugar' => '<p>Sala</p>', 'hora_inicio' => '09:00',
            'asistentes' => [['nombre' => 'Juan Pérez']], 'observaciones_generales' => '<p>—</p>',
            'hora_finalizacion' => '10:00', 'firmantes' => [['nombre' => 'Juan Pérez', 'rol' => 'Presidente']],
        ], $headers)->assertStatus(200);
        $this->postJson("/api/actas-comite/{$acta['id']}/aprobar-orden-dia", [], $headers)->assertStatus(200);

        $response = $this->postJson("/api/actas-comite/{$acta['id']}/registrar", [], $headers);
        $response->assertStatus(422);
        $this->assertStringContainsString(
            'identificación sin vincular a un cliente existente',
            implode(' ', $response->json('campos_faltantes'))
        );
        $this->assertStringContainsString(
            'tipo de solicitud no reconocido',
            implode(' ', $response->json('campos_faltantes'))
        );
        $this->assertDatabaseCount('credito_ordinarios', 1); // solo el de sistema, ninguno se creó.
    }

    /**
     * SCRUM-190 (2026-08-12, punto 1): con cliente/tipo/amortización
     * vinculables a catálogos reales, registrar() debe crear un
     * CreditoOrdinario real (con su SolicitudCredito) para la solicitud
     * manual, y ese crédito debe aparecer en Gestión de Créditos igual que
     * uno originado normalmente.
     */
    public function test_registrar_materializa_credito_ordinario_para_solicitud_manual(): void
    {
        Passport::actingAs($this->coordinador);
        $headers = ['X-Active-Role' => 'coordinador_comercial'];

        // El crédito manual necesita al menos una solicitud "de sistema"
        // para poder generar el acta — se agrega y se rechaza para no
        // interferir con el conteo de la bandeja de Gestión de Créditos.
        $creditoSistema = $this->crearCreditoEnComiteEvaluacion();
        $acta = $this->postJson('/api/actas-comite/generar', [], $headers)->json();
        $sistemaId = $acta['solicitudes'][0]['id'];

        $manual = $this->postJson("/api/actas-comite/{$acta['id']}/solicitudes", [
            'cliente_nombre' => $this->clienteNatural->nombre,
            'tipo_solicitud' => $this->tipoOrdinario->nombre,
            'monto' => 12000000,
        ], $headers)->json();

        $this->putJson("/api/actas-comite/{$acta['id']}/solicitudes/{$manual['id']}", [
            'cliente_identificacion' => $this->clienteNatural->numero_documento,
            'amortizacion' => $this->amortizacionMensual->nombre,
            'plazo_meses' => 24,
            'fuente_pago' => 'Ingresos operacionales',
            'estado_decision' => 'aprobado',
            'monto_decision' => 12000000,
        ], $headers)->assertStatus(200);

        $this->putJson("/api/actas-comite/{$acta['id']}/solicitudes/{$sistemaId}", [
            'estado_decision' => 'rechazado', 'monto_decision' => 0,
        ], $headers)->assertStatus(200);

        $this->putJson("/api/actas-comite/{$acta['id']}", [
            'fecha_reunion' => '2026-08-12', 'lugar' => '<p>Sala</p>', 'hora_inicio' => '09:00',
            'asistentes' => [['nombre' => 'Juan Pérez']], 'observaciones_generales' => '<p>—</p>',
            'hora_finalizacion' => '10:00', 'firmantes' => [['nombre' => 'Juan Pérez', 'rol' => 'Presidente']],
        ], $headers)->assertStatus(200);
        $this->postJson("/api/actas-comite/{$acta['id']}/aprobar-orden-dia", [], $headers)->assertStatus(200);

        $this->postJson("/api/actas-comite/{$acta['id']}/registrar", [], $headers)->assertStatus(200);

        $manualRefrescada = \App\Models\ActaComiteSolicitud::find($manual['id']);
        $this->assertNotNull($manualRefrescada->credito_ordinario_id);

        $creditoManual = CreditoOrdinario::find($manualRefrescada->credito_ordinario_id);
        $this->assertSame('aprobada_garantias', $creditoManual->estado);
        $this->assertSame('comite_aprobado', $creditoManual->resultado_origen);
        $this->assertSame($this->clienteNatural->id, $creditoManual->solicitudCredito->cliente_id);
        $this->assertSame($this->tipoOrdinario->id, $creditoManual->solicitudCredito->tipo_credito_id);
        $this->assertSame($this->amortizacionMensual->id, $creditoManual->solicitudCredito->amortizacion_id);

        // Aparece en Gestión de Créditos igual que un crédito normal (junto
        // con el de sistema, que quedó rechazado por el Comité).
        $bandeja = $this->getJson('/api/gestion-creditos', $headers);
        $bandeja->assertStatus(200)->assertJsonCount(2);
        $ids = collect($bandeja->json())->pluck('id');
        $this->assertTrue($ids->contains($creditoManual->id));
        $this->assertTrue($ids->contains($creditoSistema->id));
    }
}
