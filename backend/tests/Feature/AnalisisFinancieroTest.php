<?php

namespace Tests\Feature;

use App\Models\Amortizacion;
use App\Models\AnalisisFinanciero;
use App\Models\Cliente;
use App\Models\Ciudad;
use App\Models\Configuracion;
use App\Models\CreditoOrdinario;
use App\Models\Departamento;
use App\Models\DocumentType;
use App\Models\SolicitudCredito;
use App\Models\TipoCredito;
use App\Models\TipoPersona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AnalisisFinancieroTest extends TestCase
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

        $departamento = Departamento::create(['nombre' => 'Valle']);
        $ciudad = Ciudad::create(['nombre' => 'Cali', 'departamento_id' => $departamento->id]);

        $this->clienteNatural = Cliente::create([
            'tipo_persona_id' => $tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '55667788',
            'identificacion' => '55667788',
            'nombre' => 'Analisis Test',
            'nombres' => 'Analisis',
            'primer_apellido' => 'Test',
            'correo_electronico' => 'analisis.cliente@test.com',
            'telefono' => '3005556677',
            'direccion' => 'Calle Analisis 1',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Super Administrador', 'email' => 'admin.af@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'admin_af', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['superadmin'],
        ]);

        $this->coordinador = User::create([
            'name' => 'Coordinador Test', 'email' => 'coordinador.af@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'coord_af', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['coordinador_comercial'],
        ]);

        $this->gerente = User::create([
            'name' => 'Gerente Test', 'email' => 'gerente.af@test.com', 'password' => bcrypt('password'),
            'numero_documento' => 'ger_af', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['gerente'],
        ]);

        Configuracion::create([
            'clave' => 'ANALISIS_FINANCIERO_TOLERANCIA_DIFERENCIA_MM',
            'valor' => '5',
            'descripcion' => 'test',
            'grupo' => 'analisis_financiero',
            'es_secreto' => false,
        ]);
    }

    /**
     * Deja un CreditoOrdinario en 'pendiente_analisis_financiero' con
     * concepto SARLAFT favorable — la precondición ya está cubierta a
     * fondo por ListasRestrictivasSarlaftTest, así que aquí se arma
     * directamente (mismo criterio que CreditoOrdinarioTest usa para
     * saltar a un estado BPMN intermedio sin repetir todo el flujo previo).
     */
    private function crearCreditoEnAnalisisFinanciero(): CreditoOrdinario
    {
        $solicitud = SolicitudCredito::create([
            'cliente_id' => $this->clienteNatural->id,
            'usuario_registra_id' => $this->admin->id,
            'tipo_credito_id' => $this->tipoOrdinario->id,
            'monto_solicitado' => 30000000,
            'plazo_meses' => 12,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Capital de trabajo',
            'fuente_pago' => 'Ingresos operacionales',
            'correo_notificacion' => 'analisis.cliente@test.com',
            'asunto_notificacion' => 'Documentación',
            'mensaje_notificacion' => 'Adjunta los archivos.',
        ]);

        return CreditoOrdinario::create([
            'numero_solicitud' => 'CO-TEST-' . $solicitud->id,
            'cliente_id' => $this->clienteNatural->id,
            'solicitud_credito_id' => $solicitud->id,
            'monto' => 30000000,
            'plazo_meses' => 12,
            'estado' => 'pendiente_analisis_financiero',
            'sarlaft_concepto' => 'favorable',
            'documentos' => [],
        ]);
    }

    private function payloadValido(): array
    {
        return [
            'anio_inicial' => 2024,
            'cantidad_anios' => 2,
            'activo' => ['caja_bancos' => ['2024' => 1000, '2025' => 1200]],
            'pasivo' => ['proveedores' => ['2024' => 200, '2025' => 250]],
            'patrimonio' => ['capital_suscrito_pagado' => ['2024' => 800, '2025' => 950]],
            'utilidad_neta' => [
                'ingresos_ordinarios' => ['2024' => 5000, '2025' => 6000],
                'costo_ventas' => ['2024' => 3000, '2025' => 3500],
            ],
            'observaciones' => 'Análisis de prueba.',
        ];
    }

    public function test_bandeja_solo_lista_creditos_con_sarlaft_favorable_y_estado_correcto(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();

        Passport::actingAs($this->coordinador);
        $response = $this->getJson('/api/analisis-financiero', ['X-Active-Role' => 'coordinador_comercial']);
        $response->assertStatus(200);
        $this->assertContains($credito->id, array_column($response->json(), 'id'));
    }

    public function test_bandeja_no_incluye_credito_sin_concepto_favorable(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();
        $credito->update(['sarlaft_concepto' => 'desfavorable', 'estado' => 'rechazado']);

        Passport::actingAs($this->coordinador);
        $response = $this->getJson('/api/analisis-financiero', ['X-Active-Role' => 'coordinador_comercial']);
        $response->assertStatus(200);
        $this->assertNotContains($credito->id, array_column($response->json(), 'id'));
    }

    public function test_rol_no_autorizado_recibe_403(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();

        Passport::actingAs($this->gerente);
        $this->getJson("/api/analisis-financiero/{$credito->id}", ['X-Active-Role' => 'gerente'])
            ->assertStatus(403);
    }

    // SCRUM-174 — el encabezado (tipo de persona, tipo de crédito,
    // amortización) debe venir poblado en el detalle, no solo en la bandeja.
    public function test_show_incluye_tipo_persona_tipo_credito_y_amortizacion_en_el_encabezado(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();

        Passport::actingAs($this->coordinador);
        $response = $this->getJson("/api/analisis-financiero/{$credito->id}", ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200);
        $this->assertEquals('Persona Natural', $response->json('credito.solicitud_credito.cliente.tipo_persona.nombre'));
        $this->assertEquals('Crédito Ordinario', $response->json('credito.solicitud_credito.tipo_credito.nombre'));
        $this->assertEquals('Mensual', $response->json('credito.solicitud_credito.amortizacion.nombre'));
    }

    public function test_guardar_borrador_no_cambia_estado_del_credito(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();

        Passport::actingAs($this->coordinador);
        $response = $this->putJson("/api/analisis-financiero/{$credito->id}/borrador", $this->payloadValido(), [
            'X-Active-Role' => 'coordinador_comercial',
        ]);

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(1200, $response->json('calculado.activo.total_activo.2025'), 0.01);
        $this->assertDatabaseHas('credito_ordinarios', ['id' => $credito->id, 'estado' => 'pendiente_analisis_financiero']);
    }

    public function test_cantidad_de_anios_invalida_falla_422(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();

        Passport::actingAs($this->coordinador);
        $this->putJson("/api/analisis-financiero/{$credito->id}/borrador", [
            'anio_inicial' => 2024,
            'cantidad_anios' => 4,
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(422);

        $this->putJson("/api/analisis-financiero/{$credito->id}/borrador", [
            'anio_inicial' => 2024,
            'cantidad_anios' => 1,
        ], ['X-Active-Role' => 'coordinador_comercial'])->assertStatus(422);
    }

    public function test_confirmar_sin_configurar_anios_falla_422(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/analisis-financiero/{$credito->id}/confirmar", [], [
            'X-Active-Role' => 'coordinador_comercial',
        ])->assertStatus(422);
    }

    public function test_confirmar_sin_activo_ni_ingresos_falla_422(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/analisis-financiero/{$credito->id}/confirmar", [
            'anio_inicial' => 2024,
            'cantidad_anios' => 2,
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Debe diligenciar al menos Activo e Ingresos Ordinarios antes de confirmar el análisis financiero.']);
    }

    public function test_confirmar_con_diferencia_contable_fuera_de_tolerancia_falla_422(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();

        $payload = $this->payloadValido();
        // Patrimonio muy por debajo de Activo - Pasivo -> diferencia grande.
        $payload['patrimonio'] = ['capital_suscrito_pagado' => ['2024' => 10, '2025' => 10]];

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/analisis-financiero/{$credito->id}/confirmar", $payload, [
            'X-Active-Role' => 'coordinador_comercial',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('diferencia_contable', $response->json());
        $this->assertDatabaseHas('analisis_financieros', ['credito_ordinario_id' => $credito->id, 'estado' => 'borrador']);
    }

    public function test_confirmar_exitoso_sin_presentacion_comite_no_transiciona_credito(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/analisis-financiero/{$credito->id}/confirmar", $this->payloadValido(), [
            'X-Active-Role' => 'coordinador_comercial',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('confirmado', $response->json('analisis.estado'));
        $this->assertDatabaseHas('credito_ordinarios', ['id' => $credito->id, 'estado' => 'pendiente_analisis_financiero']);

        $credito->refresh();
        $historial = $credito->historial_estados;
        $ultimo = end($historial);
        $this->assertStringContainsString('Falta cargar la presentación', $ultimo['comentario']);
    }

    public function test_confirmar_exitoso_con_presentacion_comite_transiciona_a_aprobacion_presentacion(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();
        $credito->update(['documentos' => ['presentacion_comite' => 'clientes/presentacion.pdf']]);

        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/analisis-financiero/{$credito->id}/confirmar", $this->payloadValido(), [
            'X-Active-Role' => 'coordinador_comercial',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('credito_ordinarios', ['id' => $credito->id, 'estado' => 'aprobacion_presentacion']);
    }

    public function test_no_se_puede_editar_ni_reconfirmar_un_analisis_ya_confirmado(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();
        AnalisisFinanciero::create([
            'credito_ordinario_id' => $credito->id,
            'estado' => 'confirmado',
            'anio_inicial' => 2024,
            'cantidad_anios' => 2,
        ]);

        Passport::actingAs($this->coordinador);
        $this->putJson("/api/analisis-financiero/{$credito->id}/borrador", $this->payloadValido(), [
            'X-Active-Role' => 'coordinador_comercial',
        ])->assertStatus(422);

        $this->postJson("/api/analisis-financiero/{$credito->id}/confirmar", $this->payloadValido(), [
            'X-Active-Role' => 'coordinador_comercial',
        ])->assertStatus(422);
    }

    public function test_show_sigue_visible_tras_confirmar_aunque_el_credito_avance(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();
        $credito->update(['documentos' => ['presentacion_comite' => 'clientes/presentacion.pdf']]);

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/analisis-financiero/{$credito->id}/confirmar", $this->payloadValido(), [
            'X-Active-Role' => 'coordinador_comercial',
        ])->assertStatus(200);

        $credito->refresh();
        $this->assertEquals('aprobacion_presentacion', $credito->estado);

        $this->getJson("/api/analisis-financiero/{$credito->id}", ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200);
    }

    public function test_descargar_pdf_y_excel_del_analisis_financiero(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();

        Passport::actingAs($this->coordinador);
        $this->putJson("/api/analisis-financiero/{$credito->id}/borrador", $this->payloadValido(), [
            'X-Active-Role' => 'coordinador_comercial',
        ])->assertStatus(200);

        $responsePdf = $this->get("/api/analisis-financiero/{$credito->id}/descargar?formato=pdf", [
            'X-Active-Role' => 'coordinador_comercial',
        ]);
        $responsePdf->assertStatus(200);
        $responsePdf->assertHeader('content-type', 'application/pdf');

        $responseExcel = $this->get("/api/analisis-financiero/{$credito->id}/descargar?formato=excel", [
            'X-Active-Role' => 'coordinador_comercial',
        ]);
        $responseExcel->assertStatus(200);
    }

    public function test_superadmin_puede_actuar_en_cualquier_estado(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();

        Passport::actingAs($this->admin);
        $this->putJson("/api/analisis-financiero/{$credito->id}/borrador", $this->payloadValido(), [
            'X-Active-Role' => 'superadmin',
        ])->assertStatus(200);
    }

    // ---------------------------------------------------------------
    // SCRUM-162 — filas custom ad-hoc por informe (round-trip end to end).
    // ---------------------------------------------------------------

    public function test_guardar_borrador_con_fila_custom_de_activo_se_suma_y_se_relee_correctamente(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();
        $payload = $this->payloadValido();
        $payload['activo']['_custom'] = [
            ['grupo' => 'activo_corriente', 'clave' => 'custom_anticipo_especial', 'label' => 'Anticipo especial'],
        ];
        $payload['activo']['custom_anticipo_especial'] = ['2024' => 300, '2025' => 400];

        Passport::actingAs($this->coordinador);
        $response = $this->putJson("/api/analisis-financiero/{$credito->id}/borrador", $payload, [
            'X-Active-Role' => 'coordinador_comercial',
        ]);

        $response->assertStatus(200);
        // 1000 (caja_bancos) + 300 (custom) = 1300 en 2024.
        $this->assertEqualsWithDelta(1300, $response->json('calculado.activo.total_activo_corriente.2024'), 0.01);

        // Releer (GET show) debe devolver el mismo total sin perder la fila custom.
        $show = $this->getJson("/api/analisis-financiero/{$credito->id}", ['X-Active-Role' => 'coordinador_comercial']);
        $show->assertStatus(200);
        $this->assertEqualsWithDelta(1300, $show->json('calculado.activo.total_activo_corriente.2024'), 0.01);
        $this->assertEquals(
            'custom_anticipo_especial',
            $show->json('analisis.activo._custom.0.clave')
        );
    }

    public function test_guardar_borrador_con_lista_custom_vacia_no_rompe_rehidratado(): void
    {
        $credito = $this->crearCreditoEnAnalisisFinanciero();
        $payload = $this->payloadValido();
        $payload['activo']['_custom'] = [];

        Passport::actingAs($this->coordinador);
        $response = $this->putJson("/api/analisis-financiero/{$credito->id}/borrador", $payload, [
            'X-Active-Role' => 'coordinador_comercial',
        ]);

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(1000, $response->json('calculado.activo.total_activo_corriente.2024'), 0.01);

        $show = $this->getJson("/api/analisis-financiero/{$credito->id}", ['X-Active-Role' => 'coordinador_comercial']);
        $show->assertStatus(200);
        $this->assertEqualsWithDelta(1000, $show->json('calculado.activo.total_activo_corriente.2024'), 0.01);
    }
}
