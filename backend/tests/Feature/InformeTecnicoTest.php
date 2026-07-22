<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Ciudad;
use App\Models\TipoPersona;
use App\Models\DocumentType;
use App\Models\TipoCredito;
use App\Models\Amortizacion;
use App\Models\DocumentPreset;
use App\Models\DocumentRequirement;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\CreditoOrdinario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class InformeTecnicoTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $ingeniero;
    private $coordinador;
    private $operativo;
    private $gerente;
    private $tesoreria;
    private $docCC;
    private $tipoNatural;
    private $tipoConstructor;
    private $amortizacionMensual;
    private $preset;
    private $requirement;
    private $clienteNatural;
    private $departamentoValle;
    private $ciudadCali;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake();

        $this->docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $this->tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
        $this->tipoConstructor = TipoCredito::firstOrCreate(['codigo' => 'CONSTRUCTOR'], ['nombre' => 'Crédito Constructor']);
        $this->amortizacionMensual = Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);

        $this->preset = DocumentPreset::create(['nombre' => 'Preset Constructor', 'descripcion' => 'Requisitos Constructor']);
        $this->requirement = DocumentRequirement::create(['nombre' => 'Estudio de Suelos', 'activo' => true]);
        $this->preset->requirements()->attach([$this->requirement->id]);

        $this->departamentoValle = Departamento::create(['nombre' => 'Valle']);
        $this->ciudadCali = Ciudad::create(['nombre' => 'Cali', 'departamento_id' => $this->departamentoValle->id]);

        $this->clienteNatural = Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '99887766',
            'identificacion' => '99887766',
            'nombre' => 'Constructor Test',
            'nombres' => 'Constructor',
            'primer_apellido' => 'Test',
            'correo_electronico' => 'constructor@test.com',
            'telefono' => '3001112233',
            'direccion' => 'Calle Constructor 1',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true
        ]);

        $this->admin = User::create([
            'name' => 'Super Administrador',
            'email' => 'admin.informe@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'admin_it',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['superadmin']
        ]);

        $this->ingeniero = User::create([
            'name' => 'Ingeniero Test',
            'email' => 'ingeniero.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'ing001',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['ingeniero']
        ]);

        $this->coordinador = User::create([
            'name' => 'Coordinador Test',
            'email' => 'coordinador.informe@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'coord001',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['coordinador_comercial']
        ]);

        $this->operativo = User::create([
            'name' => 'Operativo Test',
            'email' => 'operativo.informe@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'oper001',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['operativo']
        ]);

        $this->gerente = User::create([
            'name' => 'Gerente Test',
            'email' => 'gerente.informe@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'ger001',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['gerente']
        ]);

        $this->tesoreria = User::create([
            'name' => 'Tesoreria Test',
            'email' => 'tesoreria.informe@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'tes001',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['tesoreria']
        ]);
    }

    /**
     * Registra una SolicitudCredito Constructor vía el endpoint real y devuelve
     * el CreditoOrdinario asociado (todavía en validacion_documental_constructor).
     */
    private function registrarSolicitudConstructor(): CreditoOrdinario
    {
        Passport::actingAs($this->admin);

        $payload = [
            'cliente_id' => $this->clienteNatural->id,
            'tipo_credito_id' => $this->tipoConstructor->id,
            'proyecto' => 'Entre Verde M+D',
            'proyecto_direccion' => 'Avenida Libertador No. 96 - 50',
            'proyecto_departamento_id' => $this->departamentoValle->id,
            'proyecto_ciudad_id' => $this->ciudadCali->id,
            'monto_solicitado' => 500000000.00,
            'plazo_meses' => 18,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Construcción de proyecto',
            'fuente_pago' => 'Ventas del proyecto',
            'correo_notificacion' => 'constructor@test.com',
            'asunto_notificacion' => 'Documentación Crédito Constructor',
            'mensaje_notificacion' => 'Por favor adjunta los archivos.',
            'document_preset_id' => $this->preset->id,
            'nombres' => 'Constructor',
            'primer_apellido' => 'Test',
            'correo_electronico' => 'constructor@test.com',
            'telefono' => '3001112233',
            'direccion' => 'Calle Constructor 1',
            'pais' => 'Colombia',
            'departamento_id' => $this->departamentoValle->id,
            'ciudad_id' => $this->ciudadCali->id
        ];

        $response = $this->postJson('/api/solicitudes-credito', $payload);
        $response->assertStatus(201);

        $clienteUser = User::where('numero_documento', '99887766')->firstOrFail();

        return CreditoOrdinario::where('cliente_id', $clienteUser->id)->firstOrFail();
    }

    /**
     * Corre el ciclo completo de aprobación de un documento (cliente sube,
     * operativo valida, gerente aprueba) usando el flujo real de client-uploads.
     */
    private function aprobarDocumentoRequerido(CreditoOrdinario $credito): void
    {
        Queue::fake();

        $clienteUser = $credito->cliente;
        $item = DocumentRequestItem::whereHas('request', function ($q) use ($credito) {
            $q->where('solicitud_credito_id', $credito->solicitud_credito_id);
        })->firstOrFail();

        Passport::actingAs($clienteUser);
        $upload = $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->create('estudio-suelos.pdf', 100, 'application/pdf'),
            'active_role' => 'cliente',
            'document_request_item_id' => $item->id,
        ])->assertStatus(200)->json();

        Passport::actingAs($this->operativo);
        $this->postJson("/api/uploads/{$upload['id']}/validate", [
            'action' => 'validar'
        ])->assertStatus(200);

        Passport::actingAs($this->gerente);
        $this->postJson("/api/uploads/{$upload['id']}/approve", [
            'action' => 'aprobar'
        ])->assertStatus(200);
    }

    /**
     * Deja el CreditoOrdinario listo en estado informe_tecnico_ingeniero,
     * con documentación 100% aprobada.
     */
    private function crearCreditoEnEstadoIngeniero(): CreditoOrdinario
    {
        $credito = $this->registrarSolicitudConstructor();
        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'validacion_documental_constructor',
        ]);
        $this->assertNotNull($credito->solicitud_credito_id);

        $this->aprobarDocumentoRequerido($credito);

        return $credito->fresh();
    }

    public function test_solicitud_constructor_crea_credito_ordinario_en_validacion_documental(): void
    {
        $credito = $this->registrarSolicitudConstructor();

        $this->assertEquals('validacion_documental_constructor', $credito->estado);
        $this->assertNotNull($credito->solicitud_credito_id);
        $this->assertDatabaseHas('document_requests', [
            'solicitud_credito_id' => $credito->solicitud_credito_id,
        ]);
    }

    /**
     * SCRUM-151: antes de este fix, ni Coordinador Comercial ni Cliente
     * tenían ninguna acción disponible en validacion_documental_constructor
     * desde esta pantalla — el único camino era el módulo separado de
     * Operaciones (/validation, ver aprobarDocumentoRequerido arriba). Ahora
     * Coordinador Comercial puede revisar y aprobar el expediente inicial
     * directamente, igual que revision_documental en Ordinario.
     */
    public function test_coordinador_comercial_aprueba_expediente_inicial_constructor_directamente(): void
    {
        $credito = $this->registrarSolicitudConstructor();
        $credito->load('solicitudCredito.documentRequest.items');
        $item = $credito->solicitudCredito->documentRequest->items->first();

        // Cliente sube el soporte requerido directamente en esta pantalla
        // (mecanismo 'documentos' JSON, no el ClientUpload de /client-upload).
        Passport::actingAs($credito->cliente);
        $this->postJson("/api/creditos/{$credito->id}/transition", [
            'accion'          => 'subir_archivo',
            'campo_documento' => 'req_item_' . $item->id,
            'archivos'        => [UploadedFile::fake()->create('estudio-suelos.pdf', 100, 'application/pdf')],
        ], ['X-Active-Role' => 'cliente'])->assertStatus(200);

        // Un rol sin autorización (operativo) no puede aprobar acá.
        Passport::actingAs($this->operativo);
        $this->postJson("/api/creditos/{$credito->id}/transition", [
            'accion' => 'aprobar',
        ], ['X-Active-Role' => 'operativo'])->assertStatus(403);

        // Coordinador Comercial aprueba el expediente completo.
        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/creditos/{$credito->id}/transition", [
            'accion' => 'aprobar',
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200);
        $response->assertJsonPath('estado', 'informe_tecnico_ingeniero');
        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'informe_tecnico_ingeniero',
        ]);
    }

    /**
     * SCRUM-151: el frontend necesita el tipo de crédito (para insertar la
     * etapa "Informe Técnico" en el checklist, solo para Constructor) y el
     * estado del Informe Técnico, sin llamadas adicionales — deben venir
     * incluidos en el detalle del crédito.
     */
    public function test_show_credito_incluye_tipo_credito_e_informe_tecnico(): void
    {
        $credito = $this->registrarSolicitudConstructor();

        Passport::actingAs($this->coordinador);
        $response = $this->getJson("/api/creditos/{$credito->id}")->assertStatus(200);

        $response->assertJsonPath('solicitud_credito.tipo_credito.codigo', 'CONSTRUCTOR');
    }

    public function test_aprobar_documentacion_transiciona_credito_a_informe_tecnico_ingeniero(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        $this->assertEquals('informe_tecnico_ingeniero', $credito->estado);
        $this->assertDatabaseHas('document_requests', [
            'solicitud_credito_id' => $credito->solicitud_credito_id,
            'estado' => 'completado',
        ]);
    }

    public function test_ingeniero_ve_bandeja_y_coordinador_no_todavia(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->ingeniero);
        $response = $this->getJson('/api/informes-tecnicos', ['X-Active-Role' => 'ingeniero']);
        $response->assertStatus(200);
        $ids = array_column($response->json(), 'id');
        $this->assertContains($credito->id, $ids);

        Passport::actingAs($this->coordinador);
        $response = $this->getJson('/api/informes-tecnicos', ['X-Active-Role' => 'coordinador_comercial']);
        $response->assertStatus(200);
        $ids = array_column($response->json(), 'id');
        $this->assertNotContains($credito->id, $ids);
    }

    public function test_ingeniero_guarda_borrador_sin_cambiar_estado_del_credito(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->ingeniero);
        $response = $this->putJson("/api/informes-tecnicos/{$credito->id}/borrador", [
            'ventas_totales_proyecto' => ['apartamentos' => 1000000],
            'costos' => ['lote' => 600000],
            'invertido' => ['lote' => 200000],
        ], ['X-Active-Role' => 'ingeniero']);

        $response->assertStatus(200);
        $this->assertEquals(1000000, $response->json('ventas_totales_proyecto.total_ventas'));
        $this->assertEquals(600000, $response->json('costos.total_costos'));
        $this->assertEquals(200000, $response->json('invertido.total_invertido'));

        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'informe_tecnico_ingeniero',
        ]);
    }

    public function test_ingeniero_registrar_sin_observaciones_falla_422(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->ingeniero);
        $response = $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'ventas_totales_proyecto' => ['total' => 1000000],
        ], ['X-Active-Role' => 'ingeniero']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'informe_tecnico_ingeniero',
        ]);
    }

    public function test_ingeniero_registrar_sin_ventas_cargadas_falla_422(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->ingeniero);
        $response = $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'observaciones_ingeniero' => 'Proyecto viable, documentación completa.',
        ], ['X-Active-Role' => 'ingeniero']);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Debe diligenciar al menos un valor en Ventas Totales Proyecto antes de registrar el informe técnico.']);
        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'informe_tecnico_ingeniero',
        ]);
    }

    public function test_ingeniero_registrar_con_observaciones_transiciona_a_coordinador(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->ingeniero);
        $response = $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'ventas_totales_proyecto' => ['apartamentos' => 1000000],
            'costos' => ['lote' => 600000],
            'invertido' => ['lote' => 200000],
            'observaciones_ingeniero' => 'Proyecto viable, documentación completa.',
        ], ['X-Active-Role' => 'ingeniero']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'informe_tecnico_coordinador',
        ]);

        // Coordinador ya puede verlo en su bandeja
        Passport::actingAs($this->coordinador);
        $response = $this->getJson('/api/informes-tecnicos', ['X-Active-Role' => 'coordinador_comercial']);
        $ids = array_column($response->json(), 'id');
        $this->assertContains($credito->id, $ids);

        // Ingeniero ya no puede seguir editando
        Passport::actingAs($this->ingeniero);
        $this->putJson("/api/informes-tecnicos/{$credito->id}/borrador", [
            'observaciones_ingeniero' => 'Intento de edición tardía',
        ], ['X-Active-Role' => 'ingeniero'])->assertStatus(403);
    }

    /**
     * Flujo completo Ingeniero -> Coordinador vía HTTP con los valores reales
     * del fixture (proyecto Entre Verde M+D) — valida que el controller arma
     * correctamente las referencias cruzadas entre secciones (ej. Coordinador
     * no vuelve a pedir Cuotas Iniciales Ya Pagadas, la toma de lo que guardó
     * el Ingeniero) y no solo el servicio de cálculo aislado.
     */
    public function test_flujo_completo_con_valores_reales_del_fixture_calcula_formulas_correctamente(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->ingeniero);
        $response = $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'ventas_totales_proyecto' => ['apartamentos' => 32841282386],
            'costos' => [
                'lote' => 5315952140,
                'directos' => 12690962100,
                'directos_urbanismo' => 865000000,
                'indirectos' => 8771866121,
                'honorarios' => 2495448000,
                'financieros' => 1596600000,
            ],
            'invertido' => [
                'lote' => 5315952140,
                'costos_directos' => 1410320211,
                'costos_indirectos' => 1660000000,
                'recursos_propios' => 2416500000,
                'cuotas_iniciales_ya_pagadas' => 3561000000,
            ],
            'observaciones_ingeniero' => 'Proyecto Entre Verde M+D, documentación completa.',
        ], ['X-Active-Role' => 'ingeniero']);

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(32841282386, $response->json('ventas_totales_proyecto.total_ventas'), 0.01);
        $this->assertEqualsWithDelta(27643780361, $response->json('costos.total_costos'), 0.01);
        $this->assertEqualsWithDelta(8386272351, $response->json('invertido.total_invertido'), 0.01);

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'credito_solicitado' => [
                'credito_solicitado' => 8000000000,
                'aptos_vendidos' => 32841282386,
                'porcentaje_cuotas_iniciales_pendientes' => 0.30,
            ],
            'saldos_por_recaudar_contraentrega' => [
                'porcentaje_cuotas_iniciales' => 0.10,
            ],
            'observaciones_coordinador' => 'Aprobado, informe consolidado.',
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200);
        // E39 = +E33: el Coordinador no volvió a mandar este dato, viene del Ingeniero.
        $this->assertEqualsWithDelta(3561000000, $response->json('credito_solicitado.cuotas_iniciales_ya_pagadas'), 0.01);
        // E40 = E38*30%-E39
        $this->assertEqualsWithDelta(6291384715.8, $response->json('credito_solicitado.cuotas_iniciales_pendientes'), 0.01);
        // E48 = E40+E41+E42
        $this->assertEqualsWithDelta(29280282386, $response->json('saldos_por_recaudar_contraentrega.total_pendiente_por_recaudar'), 0.01);
        // E57 Saldo X Financiar
        $this->assertEqualsWithDelta(4966123294.2, $response->json('analisis_financiacion.saldo_x_financiar'), 0.01);
        // G77 = E36/E9 (Apartamentos, no total de ventas)
        $this->assertEqualsWithDelta(0.24359584701876, $response->json('coberturas.cobertura_garantia.cobertura'), 0.0000001);
        $this->assertEquals('registrado', $response->json('estado'));
    }

    public function test_coordinador_guarda_borrador_y_registra_finaliza_credito(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->ingeniero);
        $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'ventas_totales_proyecto' => ['apartamentos' => 1000000],
            'observaciones_ingeniero' => 'Proyecto viable.',
        ], ['X-Active-Role' => 'ingeniero'])->assertStatus(200);

        Passport::actingAs($this->coordinador);
        $this->putJson("/api/informes-tecnicos/{$credito->id}/borrador", [
            'credito_solicitado' => ['monto' => 500000000],
            'saldos_por_recaudar_contraentrega' => ['saldo' => 100000000],
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(200);

        $response = $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'credito_solicitado' => ['monto' => 500000000],
            'saldos_por_recaudar_contraentrega' => ['saldo' => 100000000],
            'analisis_financiacion' => ['detalle' => 'ok'],
            'coberturas' => ['detalle' => 'ok'],
            'observaciones_coordinador' => 'Aprobado, informe consolidado.',
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200);
        $this->assertEquals('registrado', $response->json('estado'));

        // SCRUM-128: el expediente ya no se detiene en informe_tecnico_finalizado
        // — encadena automáticamente a sarlaft_control_interno en la misma
        // llamada (ver test_registrar_coordinador_encadena_a_sarlaft_control_interno
        // para el detalle de ese comportamiento).
        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'sarlaft_control_interno',
        ]);
        $this->assertDatabaseHas('informes_tecnicos', [
            'credito_ordinario_id' => $credito->id,
            'estado' => 'registrado',
        ]);
    }

    /**
     * SCRUM-128: al registrar el Coordinador Comercial, el hook automático
     * encadena el expediente a sarlaft_control_interno (no se detiene en
     * informe_tecnico_finalizado) y el informe técnico ya registrado sigue
     * siendo consultable — el gate de visualización de show()/descargar()
     * fue ampliado para no depender solo del estado del CreditoOrdinario.
     */
    public function test_registrar_coordinador_encadena_a_sarlaft_control_interno(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->ingeniero);
        $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'ventas_totales_proyecto' => ['apartamentos' => 1000000],
            'observaciones_ingeniero' => 'Proyecto viable.',
        ], ['X-Active-Role' => 'ingeniero'])->assertStatus(200);

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'credito_solicitado' => ['monto' => 500000000],
            'saldos_por_recaudar_contraentrega' => ['saldo' => 100000000],
            'observaciones_coordinador' => 'Aprobado, informe consolidado.',
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200);

        $credito->refresh();
        $this->assertEquals('sarlaft_control_interno', $credito->estado);

        // El historial documenta los dos saltos de estado en la misma llamada.
        $historial = $credito->historial_estados;
        $ultimosDos = array_slice($historial, -2);
        $this->assertEquals('informe_tecnico_finalizado', $ultimosDos[0]['estado_nuevo']);
        $this->assertEquals('sarlaft_control_interno', $ultimosDos[1]['estado_nuevo']);

        // El informe técnico ya registrado sigue siendo consultable/descargable
        // aunque el CreditoOrdinario avanzó más allá de los 3 estados originales.
        Passport::actingAs($this->coordinador);
        $this->getJson("/api/informes-tecnicos/{$credito->id}", ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200);

        Passport::actingAs($this->ingeniero);
        $this->getJson("/api/informes-tecnicos/{$credito->id}/descargar?formato=pdf", ['X-Active-Role' => 'ingeniero'])
            ->assertStatus(200);
    }

    public function test_usuario_sin_rol_correspondiente_recibe_403(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->tesoreria);
        $this->putJson("/api/informes-tecnicos/{$credito->id}/borrador", [
            'observaciones_ingeniero' => 'No debería poder',
        ], ['X-Active-Role' => 'tesoreria'])->assertStatus(403);

        $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'observaciones_ingeniero' => 'No debería poder',
        ], ['X-Active-Role' => 'tesoreria'])->assertStatus(403);

        // Coordinador tampoco puede actuar todavía (le toca al ingeniero primero)
        Passport::actingAs($this->coordinador);
        $this->putJson("/api/informes-tecnicos/{$credito->id}/borrador", [
            'observaciones_coordinador' => 'Muy pronto',
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(403);
    }

    public function test_coordinador_no_puede_ver_el_detalle_mientras_es_turno_del_ingeniero(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        // El ingeniero sí puede ver su propio turno.
        Passport::actingAs($this->ingeniero);
        $this->getJson("/api/informes-tecnicos/{$credito->id}", ['X-Active-Role' => 'ingeniero'])
            ->assertStatus(200);

        // El coordinador no debe poder ver el detalle todavía (evita ver un
        // borrador ajeno antes de que le corresponda).
        Passport::actingAs($this->coordinador);
        $this->getJson("/api/informes-tecnicos/{$credito->id}", ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(403);

        // Una vez que el ingeniero registra, el coordinador ya puede ver
        // (y el ingeniero sigue pudiendo, aunque ya no editar).
        Passport::actingAs($this->ingeniero);
        $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'ventas_totales_proyecto' => ['apartamentos' => 1000000],
            'observaciones_ingeniero' => 'Listo',
        ], ['X-Active-Role' => 'ingeniero'])->assertStatus(200);

        Passport::actingAs($this->coordinador);
        $this->getJson("/api/informes-tecnicos/{$credito->id}", ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200);

        Passport::actingAs($this->ingeniero);
        $this->getJson("/api/informes-tecnicos/{$credito->id}", ['X-Active-Role' => 'ingeniero'])
            ->assertStatus(200);
    }

    public function test_superadmin_puede_actuar_en_cualquier_estado(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->admin);
        $this->putJson("/api/informes-tecnicos/{$credito->id}/borrador", [
            'observaciones_ingeniero' => 'Editado por superadmin',
        ], ['X-Active-Role' => 'superadmin'])->assertStatus(200);

        $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'ventas_totales_proyecto' => ['apartamentos' => 1000000],
            'observaciones_ingeniero' => 'Editado por superadmin',
        ], ['X-Active-Role' => 'superadmin'])->assertStatus(200);

        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'informe_tecnico_coordinador',
        ]);

        $this->putJson("/api/informes-tecnicos/{$credito->id}/borrador", [
            'observaciones_coordinador' => 'Editado por superadmin',
        ], ['X-Active-Role' => 'superadmin'])->assertStatus(200);
    }

    public function test_descargar_pdf_y_excel_del_informe_tecnico(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->ingeniero);
        $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'ventas_totales_proyecto' => ['apartamentos' => 32841282386],
            'observaciones_ingeniero' => 'Proyecto viable.',
        ], ['X-Active-Role' => 'ingeniero'])->assertStatus(200);

        // El propio Ingeniero puede descargar aunque el informe siga en
        // borrador (habilitado apenas exista algún dato guardado).
        $responsePdf = $this->get("/api/informes-tecnicos/{$credito->id}/descargar?formato=pdf", [
            'X-Active-Role' => 'ingeniero'
        ]);
        $responsePdf->assertStatus(200);
        $responsePdf->assertHeader('content-type', 'application/pdf');

        $responseExcel = $this->get("/api/informes-tecnicos/{$credito->id}/descargar?formato=excel", [
            'X-Active-Role' => 'ingeniero'
        ]);
        $responseExcel->assertStatus(200);
    }

    public function test_coordinador_no_puede_descargar_mientras_es_turno_del_ingeniero(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->coordinador);
        $this->get("/api/informes-tecnicos/{$credito->id}/descargar?formato=pdf", [
            'X-Active-Role' => 'coordinador_comercial'
        ])->assertStatus(403);
    }
}
