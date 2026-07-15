<?php

namespace Tests\Feature;

use App\Mail\SarlaftDesfavorableClienteMail;
use App\Mail\SarlaftDesfavorableCoordinadorMail;
use App\Models\User;
use App\Models\Cliente;
use App\Models\TipoPersona;
use App\Models\DocumentType;
use App\Models\TipoCredito;
use App\Models\Amortizacion;
use App\Models\CreditoOrdinario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ListasRestrictivasSarlaftTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $cumplimiento;
    private $coordinador;
    private $gerente;
    private $docCC;
    private $tipoNatural;
    private $tipoOrdinario;
    private $amortizacionMensual;
    private $clienteNatural;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('public');

        $this->docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $this->tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
        $this->tipoOrdinario = TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
        $this->amortizacionMensual = Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);

        $this->clienteNatural = Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '55667788',
            'identificacion' => '55667788',
            'nombre' => 'Sarlaft Test',
            'nombres' => 'Sarlaft',
            'primer_apellido' => 'Test',
            'correo_electronico' => 'sarlaft.cliente@test.com',
            'telefono' => '3005556677',
            'direccion' => 'Calle Sarlaft 1',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true
        ]);

        $this->admin = User::create([
            'name' => 'Super Administrador', 'email' => 'admin.sarlaft@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'admin_sar', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['superadmin']
        ]);

        $this->cumplimiento = User::create([
            'name' => 'Cumplimiento Test', 'email' => 'cumplimiento.sarlaft@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'cump001', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['oficial_cumplimiento']
        ]);

        $this->coordinador = User::create([
            'name' => 'Coordinador Test', 'email' => 'coordinador.sarlaft@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'coord_sar', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['coordinador_comercial']
        ]);

        $this->gerente = User::create([
            'name' => 'Gerente Test', 'email' => 'gerente.sarlaft@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'ger_sar', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['gerente']
        ]);
    }

    /**
     * Registra una SolicitudCredito Ordinario vía el endpoint real (arranca
     * el BPMN de CreditoOrdinario en revision_documental) y la lleva hasta
     * sarlaft_control_interno, tal como lo hace la cirugía de
     * CreditoOrdinarioController::transition() (revision_documental -> aprobar).
     */
    private function crearCreditoEnSarlaftControlInterno(): CreditoOrdinario
    {
        Passport::actingAs($this->admin);

        $payload = [
            'cliente_id' => $this->clienteNatural->id,
            'tipo_credito_id' => $this->tipoOrdinario->id,
            'monto_solicitado' => 30000000.00,
            'plazo_meses' => 12,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Capital de trabajo',
            'fuente_pago' => 'Ingresos operacionales',
            'correo_notificacion' => 'sarlaft.cliente@test.com',
            'asunto_notificacion' => 'Documentación Crédito',
            'mensaje_notificacion' => 'Por favor adjunta los archivos.',
            // NOTA: SolicitudCreditoController::store() accede a
            // $validated['document_preset_id'] sin operador ?? (bug
            // preexistente, no introducido por SCRUM-128 — ver reporte
            // final). Hay que mandar la clave explícita en null para
            // no disparar "Undefined array key".
            'document_preset_id' => null,
            'nombres' => 'Sarlaft',
            'primer_apellido' => 'Test',
            'correo_electronico' => 'sarlaft.cliente@test.com',
            'telefono' => '3005556677',
            'direccion' => 'Calle Sarlaft 1',
            'pais' => 'Colombia',
            'departamento' => 'Valle',
            'ciudad' => 'Cali'
        ];

        $this->postJson('/api/solicitudes-credito', $payload)->assertStatus(201);

        $clienteUser = User::where('numero_documento', '55667788')->firstOrFail();
        $credito = CreditoOrdinario::where('cliente_id', $clienteUser->id)->firstOrFail();
        $this->assertEquals('revision_documental', $credito->estado);

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/creditos/{$credito->id}/transition", [
            'accion' => 'aprobar',
            'comentario' => 'Documentación inicial correcta.'
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'sarlaft_control_interno');

        return $credito->fresh();
    }

    private function pdf(string $name = 'sintesis.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    public function test_bandeja_filtra_por_rol(): void
    {
        $credito = $this->crearCreditoEnSarlaftControlInterno();

        Passport::actingAs($this->cumplimiento);
        $response = $this->getJson('/api/listas-sarlaft', ['X-Active-Role' => 'oficial_cumplimiento']);
        $response->assertStatus(200);
        $ids = array_column($response->json(), 'id');
        $this->assertContains($credito->id, $ids);

        Passport::actingAs($this->coordinador);
        $response = $this->getJson('/api/listas-sarlaft', ['X-Active-Role' => 'coordinador_comercial']);
        $response->assertStatus(200);
        $this->assertCount(0, $response->json());
    }

    public function test_guardar_borrador_no_transiciona_el_credito(): void
    {
        $credito = $this->crearCreditoEnSarlaftControlInterno();

        Passport::actingAs($this->cumplimiento);
        $response = $this->putJson("/api/listas-sarlaft/{$credito->id}/borrador", [
            'sarlaft_concepto' => 'favorable',
            'sarlaft_observaciones' => 'Sin coincidencias en listas restrictivas.',
        ], ['X-Active-Role' => 'oficial_cumplimiento']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'sarlaft_control_interno',
            'sarlaft_concepto' => 'favorable',
        ]);
    }

    public function test_finalizar_favorable_transiciona_a_pendiente_analisis_financiero(): void
    {
        $credito = $this->crearCreditoEnSarlaftControlInterno();

        Passport::actingAs($this->cumplimiento);
        $response = $this->postJson("/api/listas-sarlaft/{$credito->id}/finalizar", [
            'sarlaft_concepto' => 'favorable',
            'sarlaft_observaciones' => 'Sin coincidencias en listas restrictivas.',
            'archivo' => $this->pdf(),
        ], ['X-Active-Role' => 'oficial_cumplimiento']);

        $response->assertStatus(200)->assertJsonPath('estado', 'pendiente_analisis_financiero');

        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'pendiente_analisis_financiero',
            'sarlaft_concepto' => 'favorable',
            'sarlaft_diligenciado_por_id' => $this->cumplimiento->id,
        ]);

        // El concepto favorable no dispara las notificaciones de rechazo
        // (esas solo aplican al camino desfavorable).
        Mail::assertNotSent(SarlaftDesfavorableClienteMail::class);
        Mail::assertNotSent(SarlaftDesfavorableCoordinadorMail::class);
    }

    public function test_finalizar_desfavorable_transiciona_a_rechazado_y_envia_dos_correos(): void
    {
        $credito = $this->crearCreditoEnSarlaftControlInterno();

        Passport::actingAs($this->cumplimiento);
        $response = $this->postJson("/api/listas-sarlaft/{$credito->id}/finalizar", [
            'sarlaft_concepto' => 'desfavorable',
            'sarlaft_observaciones' => 'Coincidencia en lista OFAC.',
            'archivo' => $this->pdf(),
        ], ['X-Active-Role' => 'oficial_cumplimiento']);

        $response->assertStatus(200)->assertJsonPath('estado', 'rechazado');

        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $credito->id,
            'estado' => 'rechazado',
            'sarlaft_concepto' => 'desfavorable',
        ]);

        Mail::assertSent(SarlaftDesfavorableClienteMail::class, function ($mail) use ($credito) {
            return $mail->hasTo('sarlaft.cliente@test.com') && $mail->credito->id === $credito->id;
        });

        Mail::assertSent(SarlaftDesfavorableCoordinadorMail::class, function ($mail) use ($credito) {
            return $mail->hasTo('coordinador.sarlaft@test.com') && $mail->credito->id === $credito->id;
        });
    }

    public function test_finalizar_sin_concepto_falla_422(): void
    {
        $credito = $this->crearCreditoEnSarlaftControlInterno();

        Passport::actingAs($this->cumplimiento);
        $this->postJson("/api/listas-sarlaft/{$credito->id}/finalizar", [
            'sarlaft_observaciones' => 'Observación de prueba.',
            'archivo' => $this->pdf(),
        ], ['X-Active-Role' => 'oficial_cumplimiento'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Seleccione el resultado de la validación.']);
    }

    public function test_finalizar_sin_observaciones_falla_422(): void
    {
        $credito = $this->crearCreditoEnSarlaftControlInterno();

        Passport::actingAs($this->cumplimiento);
        $this->postJson("/api/listas-sarlaft/{$credito->id}/finalizar", [
            'sarlaft_concepto' => 'favorable',
            'archivo' => $this->pdf(),
        ], ['X-Active-Role' => 'oficial_cumplimiento'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Ingrese las observaciones que sustentan el concepto.']);
    }

    public function test_finalizar_sin_pdf_falla_422(): void
    {
        $credito = $this->crearCreditoEnSarlaftControlInterno();

        Passport::actingAs($this->cumplimiento);
        $this->postJson("/api/listas-sarlaft/{$credito->id}/finalizar", [
            'sarlaft_concepto' => 'favorable',
            'sarlaft_observaciones' => 'Observación de prueba.',
        ], ['X-Active-Role' => 'oficial_cumplimiento'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Adjunte el documento Síntesis Oficial de Cumplimiento en formato PDF.']);
    }

    public function test_finalizar_con_archivo_no_pdf_falla_422(): void
    {
        $credito = $this->crearCreditoEnSarlaftControlInterno();

        Passport::actingAs($this->cumplimiento);
        $this->postJson("/api/listas-sarlaft/{$credito->id}/finalizar", [
            'sarlaft_concepto' => 'favorable',
            'sarlaft_observaciones' => 'Observación de prueba.',
            'archivo' => UploadedFile::fake()->create('sintesis.txt', 10, 'text/plain'),
        ], ['X-Active-Role' => 'oficial_cumplimiento'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'El archivo debe estar en formato PDF.']);
    }

    public function test_rol_fuera_de_alcance_recibe_403(): void
    {
        $credito = $this->crearCreditoEnSarlaftControlInterno();

        Passport::actingAs($this->coordinador);
        $this->getJson("/api/listas-sarlaft/{$credito->id}", ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(403);

        $this->putJson("/api/listas-sarlaft/{$credito->id}/borrador", [
            'sarlaft_concepto' => 'favorable',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(403);

        $this->postJson("/api/listas-sarlaft/{$credito->id}/finalizar", [
            'sarlaft_concepto' => 'favorable',
            'sarlaft_observaciones' => 'x',
            'archivo' => $this->pdf(),
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(403);
    }

    public function test_estado_fuera_de_alcance_recibe_403_aunque_el_rol_sea_correcto(): void
    {
        $credito = $this->crearCreditoEnSarlaftControlInterno();

        Passport::actingAs($this->cumplimiento);
        $this->postJson("/api/listas-sarlaft/{$credito->id}/finalizar", [
            'sarlaft_concepto' => 'favorable',
            'sarlaft_observaciones' => 'Sin coincidencias en listas restrictivas.',
            'archivo' => $this->pdf(),
        ], ['X-Active-Role' => 'oficial_cumplimiento'])->assertStatus(200);

        // Ya no está en sarlaft_control_interno — el Oficial de Cumplimiento
        // ya no puede volver a actuar sobre este crédito.
        $this->putJson("/api/listas-sarlaft/{$credito->id}/borrador", [
            'sarlaft_observaciones' => 'Intento de edición tardía',
        ], ['X-Active-Role' => 'oficial_cumplimiento'])
            ->assertStatus(403);
    }

    public function test_show_no_expone_creditos_que_todavia_no_llegaron_a_la_bandeja(): void
    {
        $credito = CreditoOrdinario::create([
            'numero_solicitud' => 'CO-2026-FUERA1',
            'cliente_id' => $this->coordinador->id,
            'monto' => 10000000,
            'plazo_meses' => 12,
            'estado' => 'revision_documental',
            'documentos' => [],
            'historial_estados' => [],
        ]);

        Passport::actingAs($this->cumplimiento);
        $this->getJson("/api/listas-sarlaft/{$credito->id}", ['X-Active-Role' => 'oficial_cumplimiento'])
            ->assertStatus(404);
    }

    public function test_superadmin_puede_actuar_en_cualquier_estado(): void
    {
        $credito = $this->crearCreditoEnSarlaftControlInterno();

        Passport::actingAs($this->admin);
        $this->putJson("/api/listas-sarlaft/{$credito->id}/borrador", [
            'sarlaft_concepto' => 'favorable',
            'sarlaft_observaciones' => 'Editado por superadmin',
        ], ['X-Active-Role' => 'superadmin'])->assertStatus(200);
    }
}
