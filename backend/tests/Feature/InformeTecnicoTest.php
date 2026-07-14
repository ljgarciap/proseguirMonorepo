<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Cliente;
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
            'departamento' => 'Valle',
            'ciudad' => 'Cali'
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
            'ventas_totales_proyecto' => ['total' => 1000000],
            'costos' => ['total' => 600000],
            'invertido' => ['total' => 200000],
        ], ['X-Active-Role' => 'ingeniero']);

        $response->assertStatus(200);
        $this->assertEquals(1000000, $response->json('ventas_totales_proyecto.total'));

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

    public function test_ingeniero_registrar_con_observaciones_transiciona_a_coordinador(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->ingeniero);
        $response = $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
            'ventas_totales_proyecto' => ['total' => 1000000],
            'costos' => ['total' => 600000],
            'invertido' => ['total' => 200000],
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

    public function test_coordinador_guarda_borrador_y_registra_finaliza_credito(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->ingeniero);
        $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
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

        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'informe_tecnico_finalizado',
        ]);
        $this->assertDatabaseHas('informes_tecnicos', [
            'credito_ordinario_id' => $credito->id,
            'estado' => 'registrado',
        ]);
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

    public function test_superadmin_puede_actuar_en_cualquier_estado(): void
    {
        $credito = $this->crearCreditoEnEstadoIngeniero();

        Passport::actingAs($this->admin);
        $this->putJson("/api/informes-tecnicos/{$credito->id}/borrador", [
            'observaciones_ingeniero' => 'Editado por superadmin',
        ], ['X-Active-Role' => 'superadmin'])->assertStatus(200);

        $this->postJson("/api/informes-tecnicos/{$credito->id}/registrar", [
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
}
