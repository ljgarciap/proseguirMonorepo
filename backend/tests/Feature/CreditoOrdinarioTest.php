<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\CreditoOrdinario;
use App\Models\Cliente;
use App\Models\TipoPersona;
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
}
