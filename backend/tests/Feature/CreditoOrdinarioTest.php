<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\CreditoOrdinario;
use App\Models\Cliente;
use App\Models\TipoPersona;
use App\Models\DocumentPreset;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\DocumentRequirement;
use App\Models\SolicitudCredito;
use App\Models\TipoCredito;
use App\Models\Amortizacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreditoOrdinarioTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $cliente;
    private $coordinador;
    private $cumplimiento;
    private $operativo;
    private $gerente;
    private $comite;
    private $tesoreria;
    private $docCC;
    private $tipoNatural;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $this->tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);

        $this->cliente = User::create([
            'name' => 'Cliente Test',
            'email' => 'cliente.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '111222',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['cliente']
        ]);

        $this->coordinador = User::create([
            'name' => 'Coordinador Test',
            'email' => 'coordinador.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '222333',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['coordinador_comercial']
        ]);

        $this->cumplimiento = User::create([
            'name' => 'Cumplimiento Test',
            'email' => 'cumplimiento.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '333444',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['oficial_cumplimiento']
        ]);

        $this->operativo = User::create([
            'name' => 'Operativo Test',
            'email' => 'operativo.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '444555',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['operativo']
        ]);

        $this->gerente = User::create([
            'name' => 'Gerente Test',
            'email' => 'gerente.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '555666',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['gerente']
        ]);

        $this->comite = User::create([
            'name' => 'Comite Test',
            'email' => 'comite.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '666777',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['comite_credito']
        ]);

        $this->tesoreria = User::create([
            'name' => 'Tesoreria Test',
            'email' => 'tesoreria.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '777888',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['tesoreria']
        ]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '888999',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['superadmin']
        ]);
    }

    private function pdf(string $name = 'doc.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    private function subirArchivo(int $creditoId, string $campo, string $rol, string $nombre = 'doc.pdf'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'          => 'subir_archivo',
            'campo_documento' => $campo,
            'archivo'         => $this->pdf($nombre),
        ], ['X-Active-Role' => $rol]);
    }

    public function test_full_bpmn_transitions_and_devoluciones(): void
    {
        Passport::actingAs($this->admin);

        // 1. Create Credit Request
        $response = $this->postJson('/api/creditos', [
            'monto'       => 50000000.00,
            'plazo_meses' => 24,
            'cliente_id'  => $this->cliente->id
        ], ['X-Active-Role' => 'superadmin']);

        $response->assertStatus(201);
        $creditoId = $response->json('id');
        $this->assertDatabaseHas('credito_ordinarios', ['id' => $creditoId, 'estado' => 'revision_documental']);

        // 2. Cliente/coordinador suben el expediente inicial. Subir un archivo no
        // transiciona por sí solo (SCRUM-142) — el crédito debe permanecer en
        // revision_documental hasta que el Coordinador apruebe explícitamente.
        Passport::actingAs($this->cliente);
        $this->subirArchivo($creditoId, 'formulario_solicitud', 'cliente')
            ->assertStatus(200)
            ->assertJsonPath('estado', 'revision_documental');
        $this->subirArchivo($creditoId, 'documentos_identidad', 'cliente')
            ->assertStatus(200)
            ->assertJsonPath('estado', 'revision_documental');

        Passport::actingAs($this->coordinador);
        $this->subirArchivo($creditoId, 'estados_financieros', 'coordinador_comercial')
            ->assertStatus(200)
            ->assertJsonPath('estado', 'revision_documental');

        // Aprobar sin el expediente completo no debe transicionar (SCRUM-142).
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'aprobar',
            'comentario' => 'Intento de aprobación incompleta.'
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'revision_documental');

        $this->subirArchivo($creditoId, 'certificados_laborales', 'coordinador_comercial')
            ->assertStatus(200)
            ->assertJsonPath('estado', 'revision_documental');

        // Coordinador approves documental revision — pasa a la bandeja dedicada
        // de Listas Restrictivas y SARLAFT (SCRUM-128), fuera del alcance de este test.
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'aprobar',
            'comentario' => 'Documentación inicial correcta.'
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'sarlaft_control_interno');

        // El concepto SARLAFT favorable (probado en ListasRestrictivasSarlaftTest)
        // deja el crédito en pendiente_analisis_financiero. Simulamos ese resultado
        // directamente sobre el modelo para continuar el flujo BPMN desde ahí.
        $credito = CreditoOrdinario::find($creditoId);
        $credito->estado = 'pendiente_analisis_financiero';
        $credito->save();

        // 3. Coordinador confirma el Análisis Financiero (SCRUM-155 — reemplaza el
        // upload manual de 'analisis_financiero'; se crea directamente en estado
        // confirmado porque este test cubre las transiciones BPMN, no las reglas
        // de validación del módulo, que tienen su propia suite en
        // AnalisisFinancieroTest). SCRUM-183: confirmar el Análisis Financiero ya
        // no depende de ninguna Presentación para el Comité — transiciona directo
        // a comite_evaluacion (ver AnalisisFinancieroControllerTest, que sí pasa
        // por el endpoint real de confirmar()).
        \App\Models\AnalisisFinanciero::create([
            'credito_ordinario_id' => $creditoId,
            'estado' => 'confirmado',
            'anio_inicial' => 2024,
            'cantidad_anios' => 2,
        ]);
        $credito->estado = 'comite_evaluacion';
        $credito->save();

        // 4. SCRUM-178 retiró el botón manual de comite_credito en
        // 'comite_evaluacion' — la única salida normal de ese estado ahora es
        // Actas de Comité (ver ActaComiteTest). Acá se ejercita el atajo de
        // superadmin que se mantiene como vía de escape (documentado en
        // CreditoOrdinarioController::transition()): SCRUM-183 retiró también
        // 'aprobacion_presentacion' — 'devolver' ahora vuelve directo a
        // 'pendiente_analisis_financiero'.
        Passport::actingAs($this->admin);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'devolver',
            'comentario' => 'El monto es muy alto, ajustar propuesta'
        ], ['X-Active-Role' => 'superadmin'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'pendiente_analisis_financiero');

        // Vuelve a comite_evaluacion (simulando una nueva confirmación del
        // Análisis Financiero, mismo atajo que el paso 3).
        $credito->refresh();
        $credito->estado = 'comite_evaluacion';
        $credito->save();

        // El rol comite_credito ya no puede transicionar directamente.
        Passport::actingAs($this->comite);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'aprobar',
            'comentario' => 'Aprobado por unanimidad.'
        ], ['X-Active-Role' => 'comite_credito'])
            ->assertStatus(422);

        // Superadmin conserva el atajo manual (sube acta y aprueba).
        Passport::actingAs($this->admin);
        $this->subirArchivo($creditoId, 'acta_comite_firmada', 'superadmin', 'acta.pdf')
            ->assertStatus(200);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'aprobar',
            'comentario' => 'Aprobado por unanimidad.'
        ], ['X-Active-Role' => 'superadmin'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'formalizacion_garantias');

        // 6. Garantías: cliente sube, operativo rechaza (limpia archivo), cliente re-sube, operativo aprueba
        Passport::actingAs($this->cliente);
        $this->subirArchivo($creditoId, 'garantias_firmadas', 'cliente', 'firmadas.pdf')
            ->assertStatus(200);

        Passport::actingAs($this->operativo);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'rechazar',
            'comentario' => 'Falta firma en página 3'
        ], ['X-Active-Role' => 'operativo'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'formalizacion_garantias')
            ->assertJsonPath('documentos.garantias_firmadas', null);

        Passport::actingAs($this->cliente);
        $this->subirArchivo($creditoId, 'garantias_firmadas', 'cliente', 'firmadas_corregidas.pdf')
            ->assertStatus(200);

        Passport::actingAs($this->operativo);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'operativo'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'aprobacion_registro_cyf');

        // 7. CYF: comercial sube, gerente rechaza (limpia), comercial re-sube, gerente aprueba
        Passport::actingAs($this->coordinador);
        $this->subirArchivo($creditoId, 'registro_cyf', 'coordinador_comercial', 'cyf_soporte.pdf')
            ->assertStatus(200);

        Passport::actingAs($this->gerente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'rechazar',
            'comentario' => 'Soporte borroso'
        ], ['X-Active-Role' => 'gerente'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'aprobacion_registro_cyf')
            ->assertJsonPath('documentos.registro_cyf', null);

        Passport::actingAs($this->coordinador);
        $this->subirArchivo($creditoId, 'registro_cyf', 'coordinador_comercial', 'cyf_soporte_clear.pdf')
            ->assertStatus(200);

        Passport::actingAs($this->gerente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'gerente'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'desembolso_ingreso');

        // 8. Desembolso ingreso: operativo sube egreso y aprueba
        Passport::actingAs($this->operativo);
        $this->subirArchivo($creditoId, 'desembolso_egreso', 'operativo', 'egreso.pdf')
            ->assertStatus(200);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'operativo'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'desembolso_aprobacion');

        // 9. Gerente devuelve desembolso (limpia egreso), operativo re-sube y aprueba
        Passport::actingAs($this->gerente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'devolver',
            'comentario' => 'Monto de transferencia errado'
        ], ['X-Active-Role' => 'gerente'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'desembolso_ingreso')
            ->assertJsonPath('documentos.desembolso_egreso', null);

        Passport::actingAs($this->operativo);
        $this->subirArchivo($creditoId, 'desembolso_egreso', 'operativo', 'egreso_v2.pdf')
            ->assertStatus(200);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'operativo'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'desembolso_aprobacion');

        Passport::actingAs($this->gerente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'gerente'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'ejecucion_transferencia');

        // 10. Tesorería sube comprobante y completa el proceso BPMN
        Passport::actingAs($this->tesoreria);
        $this->subirArchivo($creditoId, 'comprobante_transferencia', 'tesoreria', 'transfer.pdf')
            ->assertStatus(200);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'tesoreria'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'completado');
    }

    // SCRUM-148: un documento subido en un ambiente (ej. test, APP_URL
    // http://173.201.39.180:8080) no debe quedar inaccesible si luego se lee
    // con un APP_URL distinto (ej. porque esa vez el deploy cayó en fallback
    // a PROD.env) — la URL se resuelve con el APP_URL vigente en el momento
    // de la lectura, no con el que estaba activo al subir el archivo.
    public function test_documento_subido_guarda_ruta_relativa_y_url_se_resuelve_con_app_url_vigente(): void
    {
        Passport::actingAs($this->admin);
        $creditoId = $this->postJson('/api/creditos', [
            'monto' => 50000000.00,
            'plazo_meses' => 24,
            'cliente_id' => $this->cliente->id,
        ], ['X-Active-Role' => 'superadmin'])->json('id');

        Passport::actingAs($this->cliente);
        $this->subirArchivo($creditoId, 'formulario_solicitud', 'cliente')->assertStatus(200);

        // En BD debe quedar solo la ruta relativa, no una URL horneada con
        // el APP_URL de este momento.
        $rawDocumentos = json_decode(
            \DB::table('credito_ordinarios')->where('id', $creditoId)->value('documentos'),
            true
        );
        $rutaRelativa = $rawDocumentos['formulario_solicitud'];
        $this->assertStringStartsNotWith('http', $rutaRelativa);
        $this->assertStringContainsString('credito_documentos/' . $creditoId . '/', $rutaRelativa);

        // Al leer el modelo, se resuelve a la URL pública del disco vigente
        // (ya no es la ruta cruda: pasó por Storage::disk('public')->url()).
        $credito = CreditoOrdinario::find($creditoId);
        $urlResuelta = $credito->documentos['formulario_solicitud'];
        $this->assertNotEquals($rutaRelativa, $urlResuelta);
        $this->assertStringContainsString('/storage/' . $rutaRelativa, $urlResuelta);

        // Simula el escenario real del ticket: el registro quedó con una URL
        // absoluta horneada con OTRO dominio (bug ya ocurrido antes del fix,
        // o dato legacy). Debe normalizarse a la MISMA URL que resolvería un
        // documento nuevo, sin rastro del dominio viejo.
        \DB::table('credito_ordinarios')->where('id', $creditoId)->update([
            'documentos' => json_encode(array_merge($rawDocumentos, [
                'formulario_solicitud' => 'http://dominio-viejo-incorrecto.test/storage/' . $rutaRelativa,
            ])),
        ]);

        $creditoConUrlVieja = CreditoOrdinario::find($creditoId);
        $this->assertEquals($urlResuelta, $creditoConUrlVieja->documentos['formulario_solicitud']);
        $this->assertStringNotContainsString('dominio-viejo-incorrecto', $creditoConUrlVieja->documentos['formulario_solicitud']);

        // Restaura el valor correcto y confirma que una transición NO
        // relacionada (otro campo) no vuelve a hornear el resto del JSON
        // como URLs absolutas.
        \DB::table('credito_ordinarios')->where('id', $creditoId)->update([
            'documentos' => json_encode($rawDocumentos),
        ]);

        $this->subirArchivo($creditoId, 'documentos_identidad', 'cliente')->assertStatus(200);

        $rawTrasSegundaSubida = json_decode(
            \DB::table('credito_ordinarios')->where('id', $creditoId)->value('documentos'),
            true
        );
        $this->assertStringStartsNotWith('http', $rawTrasSegundaSubida['formulario_solicitud']);
    }

    // SCRUM-256: antes el widget "Subir" de Etapa 1 quedaba habilitado
    // siempre, así que un segundo envío para la misma clave se apilaba sobre
    // el archivo ya cargado en documentos_raw[campo] — el mismo documento
    // terminaba mostrándose duplicado en el expediente. El endpoint debe
    // rechazar el segundo intento explícitamente (defensa en profundidad,
    // el frontend ya oculta el botón vía puedeSubirDocumento()).
    public function test_no_permite_recargar_documento_etapa1_legacy_ya_cargado(): void
    {
        Passport::actingAs($this->admin);
        $creditoId = $this->postJson('/api/creditos', [
            'monto' => 50000000.00,
            'plazo_meses' => 24,
            'cliente_id' => $this->cliente->id,
        ], ['X-Active-Role' => 'superadmin'])->json('id');

        Passport::actingAs($this->cliente);
        $this->subirArchivo($creditoId, 'formulario_solicitud', 'cliente', 'v1.pdf')
            ->assertStatus(200);

        $this->subirArchivo($creditoId, 'formulario_solicitud', 'cliente', 'v2.pdf')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este documento ya fue cargado. Si necesitas reemplazarlo, contacta al Coordinador Comercial.');

        // Solo el primer archivo quedó registrado — no se apiló el segundo.
        $credito = CreditoOrdinario::find($creditoId);
        $this->assertStringContainsString('v1.pdf', $credito->documentos_raw['formulario_solicitud']);

        // Otra clave de Etapa 1 sigue disponible con normalidad.
        $this->subirArchivo($creditoId, 'documentos_identidad', 'cliente')->assertStatus(200);
    }

    /**
     * Crea un CreditoOrdinario con Etapa 1 dirigida por preset (SCRUM-146):
     * SolicitudCredito + DocumentRequest('inicial') + 1 DocumentRequestItem,
     * mismo patrón que DocumentRequestNotificationTest.
     */
    private function creditoConPresetEtapa1(): array
    {
        $preset = DocumentPreset::create(['nombre' => 'Preset 256', 'descripcion' => 'Requisitos']);
        $requirement = DocumentRequirement::create(['nombre' => 'Documento Único', 'activo' => true]);
        $preset->requirements()->attach([$requirement->id]);

        $tipoCredito = TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
        $amortizacion = Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);

        // SolicitudCredito.cliente_id apunta a clientes.id (modelo Cliente),
        // NO a users.id como CreditoOrdinario.cliente_id — mismo nombre de
        // columna, tabla distinta (gotcha ya documentado en memoria del
        // proyecto).
        $clienteRegistro = Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '256256',
            'identificacion' => '256256',
            'nombre' => 'Cliente Preset 256',
            'nombres' => 'Cliente',
            'primer_apellido' => 'Preset256',
            'correo_electronico' => 'cliente.test@test.com',
            'telefono' => '3000000000',
            'direccion' => 'Calle 256',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true,
        ]);

        $solicitud = SolicitudCredito::create([
            'cliente_id' => $clienteRegistro->id,
            'usuario_registra_id' => $this->coordinador->id,
            'tipo_credito_id' => $tipoCredito->id,
            'monto_solicitado' => 10000000,
            'plazo_meses' => 12,
            'amortizacion_id' => $amortizacion->id,
            'destino_recurso' => 'Capital',
            'fuente_pago' => 'Ventas',
            'correo_notificacion' => 'cliente.test@test.com',
            'asunto_notificacion' => 'Asunto',
            'mensaje_notificacion' => 'Mensaje',
        ]);

        $documentRequest = DocumentRequest::create([
            'cliente_id' => $this->cliente->id,
            'creado_por' => $this->coordinador->id,
            'estado' => 'pendiente',
            'etapa' => 'inicial',
            'preset_id' => $preset->id,
            'preset_nombre' => $preset->nombre,
            'solicitud_credito_id' => $solicitud->id,
        ]);

        $item = DocumentRequestItem::create([
            'document_request_id' => $documentRequest->id,
            'document_requirement_id' => $requirement->id,
            'estado' => 'pendiente',
        ]);

        $creditoId = CreditoOrdinario::iniciar(
            clienteId: $this->cliente->id,
            monto: 10000000,
            plazoMeses: 12,
            usuario: $this->coordinador->name,
            rol: 'coordinador_comercial',
            comentario: 'Solicitud registrada.',
            solicitudCreditoId: $solicitud->id,
        )->id;

        return [$creditoId, $item];
    }

    public function test_no_permite_recargar_documento_etapa1_preset_ya_cargado(): void
    {
        [$creditoId, $item] = $this->creditoConPresetEtapa1();
        $campo = 'req_item_' . $item->id;

        Passport::actingAs($this->cliente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => $campo,
            'archivos' => [$this->pdf('v1.pdf')],
        ], ['X-Active-Role' => 'cliente'])->assertStatus(200);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => $campo,
            'archivos' => [$this->pdf('v2.pdf')],
        ], ['X-Active-Role' => 'cliente'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este documento ya fue cargado. Si necesitas reemplazarlo, contacta al Coordinador Comercial.');

        // Solo 1 archivo quedó registrado en documentos_raw, y el
        // DocumentRequestItem sigue apuntando al único ClientUpload real
        // (no se creó un segundo al rechazar la solicitud).
        $credito = CreditoOrdinario::find($creditoId);
        $this->assertCount(1, $credito->documentos_raw[$campo]);
        $item->refresh();
        $this->assertNotNull($item->client_upload_id);
    }

    // Excepción del guard: si el Coordinador marcó el ítem 'rechazado'
    // (corrección solicitada), etapa1KeySatisfecha() lo trata como "no
    // satisfecho" — el cliente debe poder volver a cargarlo.
    public function test_permite_recargar_documento_etapa1_preset_marcado_rechazado(): void
    {
        [$creditoId, $item] = $this->creditoConPresetEtapa1();
        $campo = 'req_item_' . $item->id;

        Passport::actingAs($this->cliente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => $campo,
            'archivos' => [$this->pdf('v1.pdf')],
        ], ['X-Active-Role' => 'cliente'])->assertStatus(200);

        $item->update(['estado' => 'rechazado', 'observaciones' => 'Documento ilegible']);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => $campo,
            'archivos' => [$this->pdf('v2_corregido.pdf')],
        ], ['X-Active-Role' => 'cliente'])->assertStatus(200);
    }

    /**
     * Variante de creditoConPresetEtapa1() con 2 requirements en vez de 1,
     * para probar duplicados ENTRE documentos distintos del mismo
     * expediente (a diferencia de los tests de arriba, que prueban
     * re-cargar el MISMO documento).
     */
    private function creditoConPresetEtapa1DosDocumentos(): array
    {
        $preset = DocumentPreset::create(['nombre' => 'Preset 256 dup', 'descripcion' => 'Requisitos']);
        $req1 = DocumentRequirement::create(['nombre' => 'RUT', 'activo' => true]);
        $req2 = DocumentRequirement::create(['nombre' => 'Documento de identidad', 'activo' => true]);
        $preset->requirements()->attach([$req1->id, $req2->id]);

        $tipoCredito = TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
        $amortizacion = Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);

        $clienteRegistro = Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '256257',
            'identificacion' => '256257',
            'nombre' => 'Cliente Preset 256 dup',
            'nombres' => 'Cliente',
            'primer_apellido' => 'Dup256',
            'correo_electronico' => 'cliente.dup@test.com',
            'telefono' => '3000000000',
            'direccion' => 'Calle 256',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true,
        ]);

        $solicitud = SolicitudCredito::create([
            'cliente_id' => $clienteRegistro->id,
            'usuario_registra_id' => $this->coordinador->id,
            'tipo_credito_id' => $tipoCredito->id,
            'monto_solicitado' => 10000000,
            'plazo_meses' => 12,
            'amortizacion_id' => $amortizacion->id,
            'destino_recurso' => 'Capital',
            'fuente_pago' => 'Ventas',
            'correo_notificacion' => 'cliente.dup@test.com',
            'asunto_notificacion' => 'Asunto',
            'mensaje_notificacion' => 'Mensaje',
        ]);

        $documentRequest = DocumentRequest::create([
            'cliente_id' => $this->cliente->id,
            'creado_por' => $this->coordinador->id,
            'estado' => 'pendiente',
            'etapa' => 'inicial',
            'preset_id' => $preset->id,
            'preset_nombre' => $preset->nombre,
            'solicitud_credito_id' => $solicitud->id,
        ]);

        $item1 = DocumentRequestItem::create([
            'document_request_id' => $documentRequest->id,
            'document_requirement_id' => $req1->id,
            'estado' => 'pendiente',
        ]);
        $item2 = DocumentRequestItem::create([
            'document_request_id' => $documentRequest->id,
            'document_requirement_id' => $req2->id,
            'estado' => 'pendiente',
        ]);

        $creditoId = CreditoOrdinario::iniciar(
            clienteId: $this->cliente->id,
            monto: 10000000,
            plazoMeses: 12,
            usuario: $this->coordinador->name,
            rol: 'coordinador_comercial',
            comentario: 'Solicitud registrada.',
            solicitudCreditoId: $solicitud->id,
        )->id;

        return [$creditoId, $item1, $item2];
    }

    /**
     * SCRUM-256 (comentario Juan Andrés, 2026-08-26): un archivo ya cargado
     * como "RUT" no debe poder registrarse también como "Documento de
     * identidad" del mismo expediente — el guard existente
     * (puedeSubirDocumento()/422 de re-carga) solo cubre volver a cargar EL
     * MISMO documento, no reusar un archivo entre documentos distintos.
     * Origen "Mis Créditos"/"Crédito Ordinario" (transition()).
     */
    public function test_no_permite_el_mismo_archivo_para_dos_documentos_distintos_etapa1_preset(): void
    {
        [$creditoId, $item1, $item2] = $this->creditoConPresetEtapa1DosDocumentos();

        Passport::actingAs($this->cliente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'req_item_' . $item1->id,
            'archivos' => [UploadedFile::fake()->createWithContent('rut.pdf', 'mismo contenido físico')],
        ], ['X-Active-Role' => 'cliente'])->assertStatus(200);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'req_item_' . $item2->id,
            'archivos' => [UploadedFile::fake()->createWithContent('cedula.pdf', 'mismo contenido físico')],
        ], ['X-Active-Role' => 'cliente'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este archivo ya fue cargado como "RUT". Cada documento requiere un archivo distinto.');

        $item2->refresh();
        $this->assertSame('pendiente', $item2->estado);
        $this->assertNull($item2->client_upload_id);

        // Un archivo genuinamente distinto para el 2° documento sí procede.
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'req_item_' . $item2->id,
            'archivos' => [UploadedFile::fake()->createWithContent('cedula.pdf', 'contenido genuinamente distinto')],
        ], ['X-Active-Role' => 'cliente'])->assertStatus(200);
    }
}
