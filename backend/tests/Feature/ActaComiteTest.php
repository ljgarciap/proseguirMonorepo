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
            ->assertJsonFragment(['message' => 'No existen créditos en estado Pendiente con análisis financiero realizado para incluir en el acta.']);
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
}
