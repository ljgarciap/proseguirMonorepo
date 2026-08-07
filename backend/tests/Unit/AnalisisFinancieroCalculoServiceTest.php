<?php

namespace Tests\Unit;

use App\Services\AnalisisFinancieroCalculoService;
use PHPUnit\Framework\TestCase;

/**
 * Valores de referencia extraídos manualmente del prototipo real de
 * SCRUM-155 (capturas "Prototipo_Analisis_Financiero_Credito", cliente
 * SUMATEC S.A.S. BIC, años 2024/2025) — cada assert compara contra el
 * total mostrado en la captura correspondiente, no un valor inventado.
 */
class AnalisisFinancieroCalculoServiceTest extends TestCase
{
    private AnalisisFinancieroCalculoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AnalisisFinancieroCalculoService();
    }

    private function inputsActivo(): array
    {
        return [
            'caja_bancos' => [2024 => 323, 2025 => 579],
            'cxc_comerciales' => [2024 => 45956, 2025 => 44124],
            'cxc_vinculados_economicos' => [2024 => 6935, 2025 => 0],
            'impuestos_corrientes' => [2024 => 5632, 2025 => 5517],
            'otras_cxc' => [2024 => 5561, 2025 => 5491],
            'inventarios' => [2024 => 98020, 2025 => 107863],
            'gastos_pagados_anticipado' => [2024 => 356, 2025 => 557],
            'propiedad_planta_equipo' => [2024 => 8358, 2025 => 8721],
            'inversiones_sociedades' => [2024 => 31483, 2025 => 27261],
            'cxc_vinculados_economicos_nc' => [2024 => 14722, 2025 => 21541],
            'impuesto_renta_activo' => [2024 => 900, 2025 => 802],
            'otros_activos_no_corrientes' => [2024 => 1857, 2025 => 1125],
        ];
    }

    private function inputsPasivo(): array
    {
        return [
            'obligaciones_financieras' => [2024 => 67261, 2025 => 51399],
            'obligaciones_particulares' => [2024 => 779, 2025 => 903],
            'proveedores' => [2024 => 55904, 2025 => 65271],
            'otras_cxp' => [2024 => 24021, 2025 => 32389],
            'impuestos_corrientes_pasivo' => [2024 => 8706, 2025 => 3463],
            'beneficios_empleados' => [2024 => 3052, 2025 => 2981],
            'otros_pasivos' => [2024 => 3033, 2025 => 3783],
            'pasivos_estimados_provisiones' => [2024 => 80, 2025 => 171],
            'obligaciones_financieras_lp' => [2024 => 17977, 2025 => 24429],
            'obligaciones_vinculados_lp' => [2024 => 0, 2025 => 1791],
            'impuesto_diferido_pasivo' => [2024 => 1302, 2025 => 1289],
        ];
    }

    private function inputsPatrimonio(): array
    {
        return [
            'capital_suscrito_pagado' => [2024 => 15400, 2025 => 15400],
            'reservas' => [2024 => 4466, 2025 => 4790],
            'superavit_revaluacion_activos' => [2024 => 1311, 2025 => 1311],
            'resultados_acumulados' => [2024 => 7495, 2025 => 10433],
            'resultados_acumulados_niif' => [2024 => 302, 2025 => 302],
            'resultados_ejercicio' => [2024 => 3240, 2025 => 2403],
            'ori_conversion_moneda' => [2024 => 5774, 2025 => 1070],
        ];
    }

    private function inputsUtilidadNeta(): array
    {
        return [
            'ingresos_ordinarios' => [2024 => 338199, 2025 => 327230],
            'costo_ventas' => [2024 => 247397, 2025 => 237955],
            'gastos_administracion' => [2024 => 42443, 2025 => 43434],
            'gastos_ventas_distribucion' => [2024 => 29232, 2025 => 27547],
            'ingreso_financiero' => [2024 => 8100, 2025 => 10825],
            'otros_ingresos' => [2024 => 2624, 2025 => 1204],
            'ingresos_metodo_participacion' => [2024 => 1060, 2025 => 481],
            'gasto_financiero' => [2024 => 13232, 2025 => 13925],
            'intereses' => [2024 => 13724, 2025 => 12578],
            'otros_gastos' => [2024 => 402, 2025 => 498],
            'impuesto_ganancias' => [2024 => 779, 2025 => 1314],
            'impuesto_renta' => [2024 => -467, 2025 => 84],
        ];
    }

    private function inputsCartera(): array
    {
        return [
            'cartera_corriente' => [2024 => 43072, 2025 => 41697],
            'vencida_0_60' => [2024 => 1528, 2025 => 1378],
            'vencida_mas_60' => [2024 => 726, 2025 => 128],
            'dificil_cobro' => [2024 => 2758, 2025 => 2956],
            'provision' => [2024 => -2128, 2025 => -2036],
        ];
    }

    public function test_activo_totales_dos_anios(): void
    {
        $resultado = $this->service->calcularActivo($this->inputsActivo(), [2024, 2025]);

        $this->assertEqualsWithDelta(162783, $resultado['total_activo_corriente'][2024], 0.01);
        $this->assertEqualsWithDelta(164131, $resultado['total_activo_corriente'][2025], 0.01);
        $this->assertEqualsWithDelta(57320, $resultado['total_activo_no_corriente'][2024], 0.01);
        $this->assertEqualsWithDelta(59450, $resultado['total_activo_no_corriente'][2025], 0.01);
        $this->assertEqualsWithDelta(220103, $resultado['total_activo'][2024], 0.01);
        $this->assertEqualsWithDelta(223581, $resultado['total_activo'][2025], 0.01);

        // Análisis estructural (base = Total Activo del mismo año)
        $this->assertEqualsWithDelta(0.74, $resultado['estructural']['activo_corriente'][2024], 0.001);

        // No hay variación para el primer año de la serie.
        $this->assertArrayNotHasKey(2024, $resultado['variacion']['total_activo'] ?? []);
        $this->assertEqualsWithDelta(0.0158, $resultado['variacion']['total_activo'][2025], 0.0005);
    }

    public function test_pasivo_totales_dos_anios(): void
    {
        $resultado = $this->service->calcularPasivo($this->inputsPasivo(), [2024, 2025]);

        $this->assertEqualsWithDelta(162836, $resultado['total_pasivo_corriente'][2024], 0.01);
        $this->assertEqualsWithDelta(19279, $resultado['total_pasivo_no_corriente'][2024], 0.01);
        $this->assertEqualsWithDelta(182115, $resultado['total_pasivo'][2024], 0.01);
        $this->assertEqualsWithDelta(187869, $resultado['total_pasivo'][2025], 0.01);

        // División por cero: obligaciones_vinculados_lp es 0 en 2024 -> N/A en 2025.
        $this->assertNull($resultado['variacion']['obligaciones_vinculados_lp'][2025]);
    }

    public function test_patrimonio_y_validacion_contable(): void
    {
        $activo = $this->service->calcularActivo($this->inputsActivo(), [2024, 2025]);
        $pasivo = $this->service->calcularPasivo($this->inputsPasivo(), [2024, 2025]);

        $resultado = $this->service->calcularPatrimonio(
            $this->inputsPatrimonio(),
            [2024, 2025],
            $activo['total_activo'],
            $pasivo['total_pasivo']
        );

        $this->assertEqualsWithDelta(37988, $resultado['total_patrimonio'][2024], 0.01);
        $this->assertEqualsWithDelta(35709, $resultado['total_patrimonio'][2025], 0.01);

        // Estructural de Patrimonio usa Pasivo + Patrimonio como base, no Total Activo.
        $this->assertEqualsWithDelta(0.07, $resultado['estructural']['capital_suscrito_pagado'][2024], 0.001);

        // 2024 balancea exacto; 2025 tiene una diferencia de 3 (visible en el prototipo).
        $this->assertEqualsWithDelta(0.0, $resultado['validacion_contable'][2024], 0.01);
        $this->assertEqualsWithDelta(3.0, $resultado['validacion_contable'][2025], 0.01);
    }

    public function test_utilidad_neta_cascada_completa(): void
    {
        $resultado = $this->service->calcularUtilidadNeta($this->inputsUtilidadNeta(), [2024, 2025]);

        $this->assertEqualsWithDelta(90802, $resultado['utilidad_bruta'][2024], 0.01);
        $this->assertEqualsWithDelta(19127, $resultado['utilidad_operacional'][2024], 0.01);
        $this->assertEqualsWithDelta(3553, $resultado['ganancia_antes_impuestos'][2024], 0.01);
        $this->assertEqualsWithDelta(3241, $resultado['utilidad_neta'][2024], 0.01);

        $this->assertEqualsWithDelta(89275, $resultado['utilidad_bruta'][2025], 0.01);
        $this->assertEqualsWithDelta(18294, $resultado['utilidad_operacional'][2025], 0.01);
        $this->assertEqualsWithDelta(3803, $resultado['ganancia_antes_impuestos'][2025], 0.01);
        $this->assertEqualsWithDelta(2405, $resultado['utilidad_neta'][2025], 0.01);
    }

    public function test_ori_total_sin_estructural(): void
    {
        $resultado = $this->service->calcularOri([
            'superavit_revaluacion_activos' => [2024 => -404, 2025 => 0],
            'conversion_moneda_extranjera' => [2024 => 4288, 2025 => -4703],
        ], [2024, 2025]);

        $this->assertEqualsWithDelta(3884, $resultado['total_ori'][2024], 0.01);
        $this->assertEqualsWithDelta(-4703, $resultado['total_ori'][2025], 0.01);
        $this->assertArrayNotHasKey('estructural', $resultado);
    }

    public function test_cartera_neta_incluye_provision_pero_grafico_la_excluye(): void
    {
        $resultado = $this->service->calcularCartera($this->inputsCartera(), [2024, 2025]);

        $this->assertEqualsWithDelta(45956, $resultado['cartera_neta'][2024], 0.01);
        $this->assertEqualsWithDelta(44123, $resultado['cartera_neta'][2025], 0.01);

        // Composición del gráfico excluye la provisión del denominador.
        $this->assertArrayNotHasKey('provision', $resultado['composicion_grafico']);
        $this->assertEqualsWithDelta(0.896, $resultado['composicion_grafico']['cartera_corriente'][2024], 0.001);
    }

    public function test_resumen_usa_ultimo_anio_y_variacion_vs_anterior(): void
    {
        $anios = [2024, 2025];
        $activo = $this->service->calcularActivo($this->inputsActivo(), $anios);
        $pasivo = $this->service->calcularPasivo($this->inputsPasivo(), $anios);
        $patrimonio = $this->service->calcularPatrimonio($this->inputsPatrimonio(), $anios, $activo['total_activo'], $pasivo['total_pasivo']);
        $utilidadNeta = $this->service->calcularUtilidadNeta($this->inputsUtilidadNeta(), $anios);
        $cartera = $this->service->calcularCartera($this->inputsCartera(), $anios);

        $resumen = $this->service->calcularResumen($anios, $activo, $pasivo, $patrimonio, $utilidadNeta, $cartera);

        $this->assertEquals(2025, $resumen['anio_resumen']);
        $this->assertEqualsWithDelta(223581, $resumen['total_activo'], 0.01);
        $this->assertEqualsWithDelta(187869, $resumen['total_pasivo'], 0.01);
        $this->assertEqualsWithDelta(35709, $resumen['total_patrimonio'], 0.01);
        $this->assertEqualsWithDelta(2405, $resumen['utilidad_neta'], 0.01);
        $this->assertEqualsWithDelta(44123, $resumen['cartera_neta'], 0.01);
        $this->assertEqualsWithDelta(3.0, $resumen['diferencia_contable'], 0.01);

        // Utilidad Neta cae ~25.8% de 2024 a 2025 (visible en el prototipo).
        $this->assertEqualsWithDelta(-0.258, $resumen['variacion_utilidad_neta'], 0.005);
    }

    public function test_tres_anios_agrega_columna_adicional_de_variacion(): void
    {
        $inputs = [
            'ingresos_ordinarios' => [2023 => 300000, 2024 => 338199, 2025 => 327230],
            'costo_ventas' => [2023 => 0, 2024 => 247397, 2025 => 237955],
            'gastos_administracion' => [2023 => 0, 2024 => 42443, 2025 => 43434],
            'gastos_ventas_distribucion' => [2023 => 0, 2024 => 29232, 2025 => 27547],
            'ingreso_financiero' => [2023 => 0, 2024 => 8100, 2025 => 10825],
            'otros_ingresos' => [2023 => 0, 2024 => 2624, 2025 => 1204],
            'ingresos_metodo_participacion' => [2023 => 0, 2024 => 1060, 2025 => 481],
            'gasto_financiero' => [2023 => 0, 2024 => 13232, 2025 => 13925],
            'intereses' => [2023 => 0, 2024 => 13724, 2025 => 12578],
            'otros_gastos' => [2023 => 0, 2024 => 402, 2025 => 498],
            'impuesto_ganancias' => [2023 => 0, 2024 => 779, 2025 => 1314],
            'impuesto_renta' => [2023 => 0, 2024 => -467, 2025 => 84],
        ];

        $resultado = $this->service->calcularUtilidadNeta($inputs, [2023, 2024, 2025]);

        $this->assertArrayNotHasKey(2023, $resultado['variacion']['ingresos_ordinarios'] ?? []);
        $this->assertArrayHasKey(2024, $resultado['variacion']['ingresos_ordinarios']);
        $this->assertArrayHasKey(2025, $resultado['variacion']['ingresos_ordinarios']);
        $this->assertEqualsWithDelta(3241, $resultado['utilidad_neta'][2024], 0.01);
        $this->assertEqualsWithDelta(2405, $resultado['utilidad_neta'][2025], 0.01);
    }

    public function test_division_por_cero_devuelve_null_no_excepcion(): void
    {
        $resultado = $this->service->calcularActivo([
            'caja_bancos' => [2024 => 0, 2025 => 500],
        ], [2024, 2025]);

        // Total Activo 2024 es 0 -> análisis estructural de ese año es N/A.
        $this->assertNull($resultado['estructural']['caja_bancos'][2024]);
        // Variación 2025 vs 2024=0 -> N/A, nunca #DIV/0!/#REF! ni una excepción.
        $this->assertNull($resultado['variacion']['caja_bancos'][2025]);
    }

    // ---------------------------------------------------------------
    // SCRUM-162 — filas custom ad-hoc por informe.
    // ---------------------------------------------------------------

    public function test_activo_suma_fila_custom_del_grupo_correcto(): void
    {
        $inputs = $this->inputsActivo();
        $inputs['_custom'] = [
            ['grupo' => 'activo_corriente', 'clave' => 'custom_activo_c_1', 'label' => 'Anticipo especial'],
            ['grupo' => 'activo_no_corriente', 'clave' => 'custom_activo_nc_1', 'label' => 'Otro activo especial'],
        ];
        $inputs['custom_activo_c_1'] = [2024 => 1000, 2025 => 2000];
        $inputs['custom_activo_nc_1'] = [2024 => 500, 2025 => 700];

        $sinCustom = $this->service->calcularActivo($this->inputsActivo(), [2024, 2025]);
        $conCustom = $this->service->calcularActivo($inputs, [2024, 2025]);

        // La fila custom de activo_corriente debe sumar ahí, no en no_corriente.
        $this->assertEqualsWithDelta(
            $sinCustom['total_activo_corriente'][2024] + 1000,
            $conCustom['total_activo_corriente'][2024],
            0.01
        );
        $this->assertEqualsWithDelta(
            $sinCustom['total_activo_no_corriente'][2024] + 500,
            $conCustom['total_activo_no_corriente'][2024],
            0.01
        );
        $this->assertEqualsWithDelta(
            $sinCustom['total_activo'][2024] + 1500,
            $conCustom['total_activo'][2024],
            0.01
        );

        // La fila custom aparece en `valores` y participa del análisis estructural.
        $this->assertArrayHasKey('custom_activo_c_1', $conCustom['valores']);
        $this->assertEqualsWithDelta(1000, $conCustom['valores']['custom_activo_c_1'][2024], 0.01);
        $this->assertNotNull($conCustom['estructural']['custom_activo_c_1'][2024]);
    }

    public function test_activo_sin_filas_custom_calcula_igual_que_antes(): void
    {
        // Regresión: `_custom` ausente (informes ya persistidos antes de SCRUM-162)
        // no debe cambiar ningún total existente.
        $resultado = $this->service->calcularActivo($this->inputsActivo(), [2024, 2025]);

        $this->assertEqualsWithDelta(220103, $resultado['total_activo'][2024], 0.01);
        $this->assertEqualsWithDelta(223581, $resultado['total_activo'][2025], 0.01);
    }

    public function test_activo_con_lista_custom_vacia_no_rompe_el_calculo(): void
    {
        $inputs = $this->inputsActivo();
        $inputs['_custom'] = [];

        $resultado = $this->service->calcularActivo($inputs, [2024, 2025]);

        $this->assertEqualsWithDelta(220103, $resultado['total_activo'][2024], 0.01);
        $this->assertEqualsWithDelta(223581, $resultado['total_activo'][2025], 0.01);
    }

    public function test_fila_custom_no_puede_pisar_una_clave_fija(): void
    {
        $inputs = $this->inputsActivo();
        // Intento malicioso/accidental: una fila custom que reutiliza una clave fija.
        $inputs['_custom'] = [
            ['grupo' => 'activo_corriente', 'clave' => 'caja_bancos', 'label' => 'Caja duplicada'],
        ];

        $conIntentoDuplicado = $this->service->calcularActivo($inputs, [2024, 2025]);
        $sinCustom = $this->service->calcularActivo($this->inputsActivo(), [2024, 2025]);

        // El total no cambia: 'caja_bancos' se sumó una sola vez, como concepto fijo.
        $this->assertEqualsWithDelta($sinCustom['total_activo'][2024], $conIntentoDuplicado['total_activo'][2024], 0.01);
    }

    public function test_pasivo_suma_filas_custom(): void
    {
        $inputs = $this->inputsPasivo();
        $inputs['_custom'] = [
            ['grupo' => 'pasivo_corriente', 'clave' => 'custom_pasivo_1', 'label' => 'Obligación adicional'],
        ];
        $inputs['custom_pasivo_1'] = [2024 => 300, 2025 => 400];

        $sinCustom = $this->service->calcularPasivo($this->inputsPasivo(), [2024, 2025]);
        $conCustom = $this->service->calcularPasivo($inputs, [2024, 2025]);

        $this->assertEqualsWithDelta($sinCustom['total_pasivo'][2024] + 300, $conCustom['total_pasivo'][2024], 0.01);
    }

    public function test_patrimonio_suma_fila_custom(): void
    {
        $activo = $this->service->calcularActivo($this->inputsActivo(), [2024, 2025]);
        $pasivo = $this->service->calcularPasivo($this->inputsPasivo(), [2024, 2025]);

        $inputs = $this->inputsPatrimonio();
        $inputs['_custom'] = [
            ['grupo' => 'patrimonio', 'clave' => 'custom_patrimonio_1', 'label' => 'Aporte adicional'],
        ];
        $inputs['custom_patrimonio_1'] = [2024 => 100, 2025 => 150];

        $sinCustom = $this->service->calcularPatrimonio($this->inputsPatrimonio(), [2024, 2025], $activo['total_activo'], $pasivo['total_pasivo']);
        $conCustom = $this->service->calcularPatrimonio($inputs, [2024, 2025], $activo['total_activo'], $pasivo['total_pasivo']);

        $this->assertEqualsWithDelta($sinCustom['total_patrimonio'][2024] + 100, $conCustom['total_patrimonio'][2024], 0.01);
    }

    public function test_cartera_suma_fila_custom_y_la_incluye_en_composicion_grafico(): void
    {
        $inputs = $this->inputsCartera();
        $inputs['_custom'] = [
            ['grupo' => 'cartera', 'clave' => 'custom_cartera_1', 'label' => 'Cartera especial'],
        ];
        $inputs['custom_cartera_1'] = [2024 => 1000, 2025 => 900];

        $sinCustom = $this->service->calcularCartera($this->inputsCartera(), [2024, 2025]);
        $conCustom = $this->service->calcularCartera($inputs, [2024, 2025]);

        $this->assertEqualsWithDelta($sinCustom['cartera_neta'][2024] + 1000, $conCustom['cartera_neta'][2024], 0.01);
        // La fila custom sí participa en la composición del gráfico (no es la provisión).
        $this->assertArrayHasKey('custom_cartera_1', $conCustom['composicion_grafico']);
        $this->assertArrayNotHasKey('provision', $conCustom['composicion_grafico']);
    }

    public function test_custom_de_un_grupo_no_afecta_a_otro_grupo_de_la_misma_seccion(): void
    {
        $inputs = $this->inputsActivo();
        $inputs['_custom'] = [
            ['grupo' => 'activo_corriente', 'clave' => 'custom_solo_corriente', 'label' => 'Solo corriente'],
        ];
        $inputs['custom_solo_corriente'] = [2024 => 5000, 2025 => 6000];

        $resultado = $this->service->calcularActivo($inputs, [2024, 2025]);

        $this->assertArrayNotHasKey('custom_solo_corriente', $this->service->calcularActivo($this->inputsActivo(), [2024, 2025])['valores']);
        $sinCustom = $this->service->calcularActivo($this->inputsActivo(), [2024, 2025]);
        $this->assertEqualsWithDelta($sinCustom['total_activo_no_corriente'][2024], $resultado['total_activo_no_corriente'][2024], 0.01);
    }

    // ---------------------------------------------------------------
    // SCRUM-187 (segunda entrega) — ocultar cualquier concepto fijo
    // (no solo eliminar filas custom) y Estado de Resultados con los
    // mismos buckets extensibles que Activo/Pasivo/Patrimonio/Cartera.
    // ---------------------------------------------------------------

    public function test_ocultar_concepto_fijo_lo_excluye_del_total_y_de_valores(): void
    {
        $inputs = $this->inputsActivo();
        $inputs['_ocultos'] = ['caja_bancos'];

        $sinOcultar = $this->service->calcularActivo($this->inputsActivo(), [2024, 2025]);
        $conOculto = $this->service->calcularActivo($inputs, [2024, 2025]);

        // caja_bancos = 323 en 2024 (ver inputsActivo) — se resta del total.
        $this->assertEqualsWithDelta($sinOcultar['total_activo'][2024] - 323, $conOculto['total_activo'][2024], 0.01);
        $this->assertArrayNotHasKey('caja_bancos', $conOculto['valores']);
    }

    public function test_ocultos_ausente_no_cambia_ningun_total_existente(): void
    {
        // Regresión: análisis ya persistidos antes de SCRUM-187 no tienen
        // `_ocultos` en su JSON — debe comportarse exactamente igual que hoy.
        $resultado = $this->service->calcularActivo($this->inputsActivo(), [2024, 2025]);

        $this->assertEqualsWithDelta(220103, $resultado['total_activo'][2024], 0.01);
    }

    public function test_ocultar_una_fila_custom_tambien_la_excluye(): void
    {
        $inputs = $this->inputsActivo();
        $inputs['_custom'] = [
            ['grupo' => 'activo_corriente', 'clave' => 'custom_activo_c_1', 'label' => 'Anticipo especial'],
        ];
        $inputs['custom_activo_c_1'] = [2024 => 1000, 2025 => 2000];
        $inputs['_ocultos'] = ['custom_activo_c_1'];

        $resultado = $this->service->calcularActivo($inputs, [2024, 2025]);
        $sinCustomNiOcultos = $this->service->calcularActivo($this->inputsActivo(), [2024, 2025]);

        $this->assertEqualsWithDelta($sinCustomNiOcultos['total_activo'][2024], $resultado['total_activo'][2024], 0.01);
        $this->assertArrayNotHasKey('custom_activo_c_1', $resultado['valores']);
    }

    public function test_utilidad_neta_con_fila_custom_en_bucket_correcto_afecta_la_cascada(): void
    {
        $inputs = $this->inputsUtilidadNeta();
        $inputs['_custom'] = [
            ['grupo' => 'gastos_operacionales', 'clave' => 'custom_gasto_extra', 'label' => 'Gasto operacional extra'],
        ];
        $inputs['custom_gasto_extra'] = [2024 => 1000, 2025 => 0];

        $sinCustom = $this->service->calcularUtilidadNeta($this->inputsUtilidadNeta(), [2024, 2025]);
        $conCustom = $this->service->calcularUtilidadNeta($inputs, [2024, 2025]);

        // Un gasto operacional extra de 1000 en 2024 resta 1000 desde
        // utilidad_operacional en adelante (bruta no se ve afectada, es
        // anterior en la cascada al bucket de gastos operacionales).
        $this->assertEqualsWithDelta($sinCustom['utilidad_bruta'][2024], $conCustom['utilidad_bruta'][2024], 0.01);
        $this->assertEqualsWithDelta($sinCustom['utilidad_operacional'][2024] - 1000, $conCustom['utilidad_operacional'][2024], 0.01);
        $this->assertEqualsWithDelta($sinCustom['utilidad_neta'][2024] - 1000, $conCustom['utilidad_neta'][2024], 0.01);
        // 2025 no tiene el gasto extra (0) — no cambia.
        $this->assertEqualsWithDelta($sinCustom['utilidad_neta'][2025], $conCustom['utilidad_neta'][2025], 0.01);
    }

    public function test_utilidad_neta_ocultar_concepto_fijo_lo_saca_de_la_cascada(): void
    {
        $inputs = $this->inputsUtilidadNeta();
        $inputs['_ocultos'] = ['impuesto_renta'];

        $sinOcultar = $this->service->calcularUtilidadNeta($this->inputsUtilidadNeta(), [2024, 2025]);
        $conOculto = $this->service->calcularUtilidadNeta($inputs, [2024, 2025]);

        // impuesto_renta 2024 = -467 (ver inputsUtilidadNeta) — al ocultarlo
        // deja de restarse (era una resta de un valor negativo, es decir
        // sumaba): utilidad_neta baja en 467.
        $this->assertEqualsWithDelta($sinOcultar['utilidad_neta'][2024] - 467, $conOculto['utilidad_neta'][2024], 0.01);
        $this->assertArrayNotHasKey('impuesto_renta', $conOculto['valores']);
    }

    public function test_utilidad_neta_sin_ocultos_ni_custom_calcula_igual_que_antes(): void
    {
        // Regresión: el refactor a buckets (SCRUM-187) no debe cambiar el
        // resultado del caso base ya cubierto por test_utilidad_neta_cascada_completa.
        $resultado = $this->service->calcularUtilidadNeta($this->inputsUtilidadNeta(), [2024, 2025]);

        $this->assertEqualsWithDelta(90802, $resultado['utilidad_bruta'][2024], 0.01);
        $this->assertEqualsWithDelta(3241, $resultado['utilidad_neta'][2024], 0.01);
        $this->assertEqualsWithDelta(2405, $resultado['utilidad_neta'][2025], 0.01);
    }
}
