<?php

namespace Tests\Unit;

use App\Services\InformeTecnicoCalculoService;
use PHPUnit\Framework\TestCase;

/**
 * Valores de referencia extraídos con PhpSpreadsheet directamente del
 * fixture real tests/Fixtures/informe-tecnico-entre-verde.xlsx (hoja
 * "ENTREVERDE", proyecto Entre Verde M+D) — ver docs/designs/
 * scrum-120-fase2-formulas-y-fidelidad-prototipo.md, sección "Fixture
 * de prueba". Cada assert compara contra el valor calculado real de la
 * celda correspondiente del Excel (no un valor inventado).
 */
class InformeTecnicoCalculoServiceTest extends TestCase
{
    private InformeTecnicoCalculoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InformeTecnicoCalculoService();
    }

    public function test_ventas_totales_proyecto_solo_apartamentos(): void
    {
        $resultado = $this->service->calcularVentasTotalesProyecto([
            'apartamentos' => 32841282386,
        ]);

        $this->assertEqualsWithDelta(32841282386, $resultado['total_ventas'], 0.01);
        $this->assertEqualsWithDelta(1.0, $resultado['porcentajes']['apartamentos'], 0.000001);
        $this->assertEqualsWithDelta(0.0, $resultado['porcentajes']['casas'], 0.000001);
    }

    public function test_costos_no_suma_honorarios_ni_financieros(): void
    {
        $totalVentas = 32841282386;

        $resultado = $this->service->calcularCostos([
            'lote' => 5315952140,
            'directos' => 12690962100,
            'directos_urbanismo' => 865000000,
            'indirectos' => 8771866121,
            'honorarios' => 2495448000,
            'incremento_costos' => 0,
            'financieros' => 1596600000,
        ], $totalVentas);

        // E15 = E16+E17+E18+E19+E22 = 27,643,780,361 (excluye Honorarios E20 y Financieros E23)
        $this->assertEqualsWithDelta(27643780361, $resultado['total_costos'], 0.01);
        // F15 = E15/E7
        $this->assertEqualsWithDelta(0.8417387614798, $resultado['porcentaje_costos'], 0.0000001);
        // Los inputs se capturan aunque no se sumen
        $this->assertEquals(2495448000, $resultado['honorarios']);
        $this->assertEquals(1596600000, $resultado['financieros']);
    }

    public function test_invertido_total_y_total_fuentes(): void
    {
        $totalVentas = 32841282386;

        $resultado = $this->service->calcularInvertido([
            'lote' => 5315952140,
            'costos_directos' => 1410320211,
            'costos_indirectos' => 1660000000,
            'recursos_propios' => 2416500000,
            'cuotas_iniciales_ya_pagadas' => 3561000000,
        ], $totalVentas);

        // E25 = E26+E27+E28 = 8,386,272,351
        $this->assertEqualsWithDelta(8386272351, $resultado['total_invertido'], 0.01);
        // F25 = E25/E7
        $this->assertEqualsWithDelta(0.25535763958398, $resultado['porcentaje_invertido'], 0.0000001);
        // E34 = SUM(E32:E33) = 5,977,500,000
        $this->assertEqualsWithDelta(5977500000, $resultado['total_fuentes'], 0.01);
    }

    public function test_credito_solicitado_referencia_cuotas_iniciales_del_ingeniero(): void
    {
        $totalVentas = 32841282386;
        $cuotasInicialesYaPagadas = 3561000000; // Invertido.cuotas_iniciales_ya_pagadas (E33)

        $resultado = $this->service->calcularCreditoSolicitado([
            'credito_solicitado' => 8000000000,
            'aptos_vendidos' => 32841282386,
            'porcentaje_cuotas_iniciales_pendientes' => 0.30,
        ], $totalVentas, $cuotasInicialesYaPagadas);

        // F36 = E36/E7
        $this->assertEqualsWithDelta(0.24359584701876, $resultado['porcentaje_sobre_ventas'], 0.0000001);
        // F38 = E38/E7
        $this->assertEqualsWithDelta(1.0, $resultado['porcentaje_aptos_vendidos_sobre_ventas'], 0.000001);
        // E39 = +E33
        $this->assertEquals(3561000000, $resultado['cuotas_iniciales_ya_pagadas']);
        // E40 = E38*30%-E39
        $this->assertEqualsWithDelta(6291384715.8, $resultado['cuotas_iniciales_pendientes'], 0.01);
        // E41 = E38-E39-E40
        $this->assertEqualsWithDelta(22988897670.2, $resultado['saldo_recaudar_contraentrega_vendidos'], 0.01);
    }

    public function test_saldos_por_recaudar_contraentrega_por_vender_y_total_pendiente(): void
    {
        $totalVentas = 32841282386;
        $aptosVendidos = 32841282386; // 100% vendido en el ejemplo, Aptos X Vender = 0

        $creditoSolicitadoCalculado = $this->service->calcularCreditoSolicitado([
            'credito_solicitado' => 8000000000,
            'aptos_vendidos' => $aptosVendidos,
            'porcentaje_cuotas_iniciales_pendientes' => 0.30,
        ], $totalVentas, 3561000000);

        $resultado = $this->service->calcularSaldosPorRecaudarContraentrega([
            'porcentaje_cuotas_iniciales' => 0.10,
        ], $totalVentas, $aptosVendidos, $creditoSolicitadoCalculado);

        // E42 = E7-E38 = 0
        $this->assertEqualsWithDelta(0, $resultado['aptos_x_vender'], 0.01);
        // E43 = E42*10% = 0
        $this->assertEqualsWithDelta(0, $resultado['cuotas_iniciales'], 0.01);
        // E45 = E42-E43-E44 = 0
        $this->assertEqualsWithDelta(0, $resultado['saldo_recaudar_contraentrega_por_vender'], 0.01);
        // E48 = E40+E41+E42 = 6,291,384,715.8 + 22,988,897,670.2 + 0 = 29,280,282,386
        $this->assertEqualsWithDelta(29280282386, $resultado['total_pendiente_por_recaudar'], 0.01);
    }

    public function test_analisis_financiacion_completo(): void
    {
        $costos = ['total_costos' => 27643780361];
        $invertido = ['total_invertido' => 8386272351];
        $creditoSolicitado = [
            'credito_solicitado' => 8000000000,
            'cuotas_iniciales_pendientes' => 6291384715.8,
        ];
        $saldosPorRecaudar = ['cuotas_iniciales' => 0];

        $resultado = $this->service->calcularAnalisisFinanciacion($costos, $invertido, $creditoSolicitado, $saldosPorRecaudar);

        // E55 = E52-E53-E54 = 11,257,508,010
        $this->assertEqualsWithDelta(11257508010, $resultado['saldo_x_financiar_bruto'], 0.01);
        // E57 = E55-E56 = 4,966,123,294.2
        $this->assertEqualsWithDelta(4966123294.2, $resultado['saldo_x_financiar'], 0.01);
        // E59 = E57-E58 = 4,966,123,294.2 (E58 = +E43 = 0 en este ejemplo)
        $this->assertEqualsWithDelta(4966123294.2, $resultado['saldo_no_financiado'], 0.01);
    }

    public function test_coberturas_usa_apartamentos_no_total_ventas_en_garantia(): void
    {
        $creditoSolicitado = [
            'credito_solicitado' => 8000000000,
            'aptos_vendidos' => 32841282386, // = Apartamentos (E9) en este ejemplo
            'saldo_recaudar_contraentrega_vendidos' => 22988897670.2,
        ];
        $analisisFinanciacion = [
            'saldo_x_financiar' => 4966123294.2,
            'saldo_no_financiado' => 4966123294.2,
        ];
        $saldosPorRecaudar = ['saldo_recaudar_contraentrega_por_vender' => 0];
        $ventasTotalesProyecto = ['apartamentos' => 32841282386];

        $resultado = $this->service->calcularCoberturas($creditoSolicitado, $analisisFinanciacion, $saldosPorRecaudar, $ventasTotalesProyecto);

        // G66 = F66/F67 = (E36+E57)/E41
        $this->assertEqualsWithDelta(0.56401674757149, $resultado['peor_escenario']['cobertura'], 0.0000001);
        $this->assertEquals('verde', $resultado['peor_escenario']['semaforo']);

        // G72 = F72/F73 = (E36+E59)/(E41+E45)
        $this->assertEqualsWithDelta(0.56401674757149, $resultado['mejor_escenario']['cobertura'], 0.0000001);
        $this->assertEquals('verde', $resultado['mejor_escenario']['semaforo']);

        // G77 = F77/F78 = E36/E9 (Apartamentos, no total de ventas)
        $this->assertEqualsWithDelta(0.24359584701876, $resultado['cobertura_garantia']['cobertura'], 0.0000001);
        $this->assertEquals('verde', $resultado['cobertura_garantia']['semaforo']);
    }

    public function test_coberturas_garantia_usa_apartamentos_de_ventas_no_aptos_vendidos_del_coordinador(): void
    {
        // Caso donde Apartamentos (E9, Ingeniero) y Aptos. Vendidos (E38,
        // Coordinador) son DISTINTOS — no se vendió el 100% del proyecto.
        // El fixture real (Entre Verde) los tenía iguales por casualidad,
        // lo que ocultó un bug donde calcularCoberturas() leía por error
        // aptos_vendidos en vez de apartamentos para este cálculo.
        $creditoSolicitado = [
            'credito_solicitado' => 8000000000,
            'aptos_vendidos' => 20000000000, // distinto de apartamentos a propósito
            'saldo_recaudar_contraentrega_vendidos' => 1,
        ];
        $analisisFinanciacion = ['saldo_x_financiar' => 0, 'saldo_no_financiado' => 0];
        $saldosPorRecaudar = ['saldo_recaudar_contraentrega_por_vender' => 0];
        $ventasTotalesProyecto = ['apartamentos' => 40000000000];

        $resultado = $this->service->calcularCoberturas($creditoSolicitado, $analisisFinanciacion, $saldosPorRecaudar, $ventasTotalesProyecto);

        // G77 = E36/E9 = 8,000,000,000 / 40,000,000,000 = 0.2 (usando apartamentos, no aptos_vendidos)
        $this->assertEqualsWithDelta(0.2, $resultado['cobertura_garantia']['cobertura'], 0.0000001);
        $this->assertEquals(40000000000, $resultado['cobertura_garantia']['denominador']);
    }

    public function test_division_por_cero_no_produce_nan_ni_infinito(): void
    {
        $resultado = $this->service->calcularVentasTotalesProyecto([]);

        $this->assertEquals(0.0, $resultado['total_ventas']);
        $this->assertEquals(0.0, $resultado['porcentajes']['casas']);
    }

    // ---------------------------------------------------------------
    // SCRUM-162 — filas custom ad-hoc por informe.
    // ---------------------------------------------------------------

    public function test_ventas_totales_proyecto_suma_filas_custom(): void
    {
        $resultado = $this->service->calcularVentasTotalesProyecto([
            'apartamentos' => 1000,
            'casas' => 500,
            '_custom' => [
                ['clave' => 'custom_terrazas', 'label' => 'Terrazas', 'valor' => 300],
                ['clave' => 'custom_bodegas', 'label' => 'Bodegas', 'valor' => 200],
            ],
        ]);

        $this->assertEqualsWithDelta(2000, $resultado['total_ventas'], 0.01);
        $this->assertCount(2, $resultado['custom']);
        $this->assertEqualsWithDelta(0.15, $resultado['porcentajes']['custom_terrazas'], 0.0001);
        $this->assertEquals('Terrazas', $resultado['custom'][0]['label']);
    }

    public function test_ventas_totales_proyecto_sin_custom_calcula_igual_que_antes(): void
    {
        $resultado = $this->service->calcularVentasTotalesProyecto(['apartamentos' => 32841282386]);

        $this->assertEqualsWithDelta(32841282386, $resultado['total_ventas'], 0.01);
        $this->assertSame([], $resultado['custom']);
    }

    public function test_ventas_totales_proyecto_con_lista_custom_vacia_no_rompe(): void
    {
        $resultado = $this->service->calcularVentasTotalesProyecto([
            'apartamentos' => 1000,
            '_custom' => [],
        ]);

        $this->assertEqualsWithDelta(1000, $resultado['total_ventas'], 0.01);
        $this->assertSame([], $resultado['custom']);
    }

    public function test_costos_suma_filas_custom_al_total_pero_honorarios_financieros_siguen_sin_sumar(): void
    {
        $totalVentas = 10000;

        $resultado = $this->service->calcularCostos([
            'lote' => 1000,
            'directos' => 500,
            'honorarios' => 999,
            'financieros' => 888,
            '_custom' => [
                ['clave' => 'custom_permiso_especial', 'label' => 'Permiso especial', 'valor' => 250],
            ],
        ], $totalVentas);

        // 1000 + 500 + 250 (custom) = 1750 — honorarios/financieros siguen excluidos.
        $this->assertEqualsWithDelta(1750, $resultado['total_costos'], 0.01);
        $this->assertCount(1, $resultado['custom']);
    }

    public function test_costos_sin_custom_calcula_igual_que_antes(): void
    {
        $resultado = $this->service->calcularCostos([
            'lote' => 5315952140,
            'directos' => 12690962100,
            'directos_urbanismo' => 865000000,
            'indirectos' => 8771866121,
            'honorarios' => 2495448000,
            'incremento_costos' => 0,
            'financieros' => 1596600000,
        ], 32841282386);

        $this->assertEqualsWithDelta(27643780361, $resultado['total_costos'], 0.01);
        $this->assertSame([], $resultado['custom']);
    }

    public function test_filas_custom_con_clave_duplicada_se_deduplican(): void
    {
        $resultado = $this->service->calcularVentasTotalesProyecto([
            '_custom' => [
                ['clave' => 'custom_x', 'label' => 'Primera', 'valor' => 100],
                ['clave' => 'custom_x', 'label' => 'Segunda (duplicada)', 'valor' => 999],
            ],
        ]);

        $this->assertCount(1, $resultado['custom']);
        $this->assertEqualsWithDelta(100, $resultado['total_ventas'], 0.01);
    }
}
