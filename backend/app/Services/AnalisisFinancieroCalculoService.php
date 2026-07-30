<?php

namespace App\Services;

/**
 * SCRUM-155 — cálculos del Análisis Financiero (Coordinador Comercial).
 *
 * Contrato: el frontend manda solo los inputs crudos de cada pestaña (por
 * concepto y por año); este servicio siempre recalcula subtotales, totales,
 * análisis estructural y variación relativa antes de persistir — nunca se
 * confía en un total calculado por el cliente (mismo criterio que
 * InformeTecnicoCalculoService).
 *
 * División por cero (RF-12/CA-14/FA-07): análisis estructural y variación
 * relativa devuelven `null` (no 0, no excepción) cuando el denominador es
 * cero — el frontend debe mostrar "N/A", nunca #DIV/0!/#REF!.
 */
class AnalisisFinancieroCalculoService
{
    private const ACTIVO_CORRIENTE = [
        'caja_bancos', 'cxc_comerciales', 'cxc_vinculados_economicos',
        'impuestos_corrientes', 'otras_cxc', 'inventarios', 'gastos_pagados_anticipado',
    ];

    private const ACTIVO_NO_CORRIENTE = [
        'propiedad_planta_equipo', 'construcciones_en_curso', 'inversiones_subsidiarias',
        'otras_cxc_no_corrientes', 'inversion_proyectos_solares', 'otros_activos_no_financieros',
        'impuesto_diferido', 'gastos_pagados_anticipado_nc', 'inversion_posicionamiento_marca',
        'valorizaciones', 'inversiones_sociedades', 'cxc_vinculados_economicos_nc',
        'impuesto_renta_activo', 'otros_activos_no_corrientes',
    ];

    private const PASIVO_CORRIENTE = [
        'obligaciones_financieras', 'obligaciones_particulares', 'proveedores',
        'otras_cxp', 'impuestos_corrientes_pasivo', 'beneficios_empleados',
        'otros_pasivos', 'pasivos_estimados_provisiones',
    ];

    private const PASIVO_NO_CORRIENTE = [
        'obligaciones_financieras_lp', 'obligaciones_vinculados_lp', 'impuesto_diferido_pasivo',
    ];

    private const PATRIMONIO_CONCEPTOS = [
        'capital_suscrito_pagado', 'reservas', 'superavit_revaluacion_activos',
        'resultados_acumulados', 'resultados_acumulados_niif', 'resultados_ejercicio',
        'ori_conversion_moneda',
    ];

    private const ORI_CONCEPTOS = ['superavit_revaluacion_activos', 'conversion_moneda_extranjera'];

    private const CARTERA_CONCEPTOS = [
        'cartera_corriente', 'vencida_0_60', 'vencida_mas_60', 'dificil_cobro', 'provision',
    ];

    /**
     * Activo Corriente + No Corriente (celdas de la plantilla SUMATEC).
     * Análisis estructural base = Total Activo del mismo año.
     */
    public function calcularActivo(array $inputs, array $anios): array
    {
        $corriente = $this->sumarGrupo($inputs, $this->conceptosConCustom($inputs, self::ACTIVO_CORRIENTE, 'activo_corriente'), $anios);
        $noCorriente = $this->sumarGrupo($inputs, $this->conceptosConCustom($inputs, self::ACTIVO_NO_CORRIENTE, 'activo_no_corriente'), $anios);

        $totalActivo = $this->sumarSubtotales([$corriente['subtotal'], $noCorriente['subtotal']], $anios);

        $baseEstructural = array_merge(
            $corriente['valores'],
            $noCorriente['valores'],
            ['activo_corriente' => $corriente['subtotal'], 'activo_no_corriente' => $noCorriente['subtotal']]
        );

        return [
            'anios' => $anios,
            'valores' => array_merge($corriente['valores'], $noCorriente['valores']),
            'total_activo_corriente' => $corriente['subtotal'],
            'total_activo_no_corriente' => $noCorriente['subtotal'],
            'total_activo' => $totalActivo,
            'estructural' => $this->estructuralGrupo($baseEstructural, $totalActivo, $anios),
            'variacion' => $this->variacionGrupo(array_merge($baseEstructural, ['total_activo' => $totalActivo]), $anios),
        ];
    }

    /**
     * Pasivo Corriente + No Corriente. Análisis estructural base = Total
     * Pasivo del mismo año.
     */
    public function calcularPasivo(array $inputs, array $anios): array
    {
        $corriente = $this->sumarGrupo($inputs, $this->conceptosConCustom($inputs, self::PASIVO_CORRIENTE, 'pasivo_corriente'), $anios);
        $noCorriente = $this->sumarGrupo($inputs, $this->conceptosConCustom($inputs, self::PASIVO_NO_CORRIENTE, 'pasivo_no_corriente'), $anios);

        $totalPasivo = $this->sumarSubtotales([$corriente['subtotal'], $noCorriente['subtotal']], $anios);

        $baseEstructural = array_merge(
            $corriente['valores'],
            $noCorriente['valores'],
            ['pasivo_corriente' => $corriente['subtotal'], 'pasivo_no_corriente' => $noCorriente['subtotal']]
        );

        return [
            'anios' => $anios,
            'valores' => array_merge($corriente['valores'], $noCorriente['valores']),
            'total_pasivo_corriente' => $corriente['subtotal'],
            'total_pasivo_no_corriente' => $noCorriente['subtotal'],
            'total_pasivo' => $totalPasivo,
            'estructural' => $this->estructuralGrupo($baseEstructural, $totalPasivo, $anios),
            'variacion' => $this->variacionGrupo(array_merge($baseEstructural, ['total_pasivo' => $totalPasivo]), $anios),
        ];
    }

    /**
     * Patrimonio + validación contable. Análisis estructural base = Total
     * Pasivo + Total Patrimonio del mismo año (así lo define el ticket,
     * no Total Activo — confirmado contra el prototipo: Capital suscrito
     * 15.400 / (182.115 pasivo + 37.988 patrimonio) = 7,0%).
     */
    public function calcularPatrimonio(array $inputs, array $anios, array $totalActivo, array $totalPasivo): array
    {
        $grupo = $this->sumarGrupo($inputs, $this->conceptosConCustom($inputs, self::PATRIMONIO_CONCEPTOS, 'patrimonio'), $anios);
        $totalPatrimonio = $grupo['subtotal'];

        $pasivoMasPatrimonio = [];
        $validacionContable = [];
        foreach ($anios as $anio) {
            $pasivoMasPatrimonio[$anio] = $this->round(($totalPasivo[$anio] ?? 0.0) + $totalPatrimonio[$anio]);
            $validacionContable[$anio] = $this->round(($totalActivo[$anio] ?? 0.0) - $pasivoMasPatrimonio[$anio]);
        }

        return [
            'anios' => $anios,
            'valores' => $grupo['valores'],
            'total_patrimonio' => $totalPatrimonio,
            'total_pasivo_mas_patrimonio' => $pasivoMasPatrimonio,
            'validacion_contable' => $validacionContable,
            'estructural' => $this->estructuralGrupo($grupo['valores'], $pasivoMasPatrimonio, $anios),
            'variacion' => $this->variacionGrupo(array_merge($grupo['valores'], ['total_patrimonio' => $totalPatrimonio]), $anios),
        ];
    }

    /**
     * Estado de resultados (Utilidad Neta) — cascada, no suma de grupo.
     * Análisis estructural base = Ingresos Ordinarios del mismo año.
     */
    public function calcularUtilidadNeta(array $inputs, array $anios): array
    {
        $valores = [];
        $utilidadBruta = [];
        $utilidadOperacional = [];
        $gananciaAntesImpuestos = [];
        $utilidadNeta = [];

        $conceptos = [
            'ingresos_ordinarios', 'costo_ventas', 'gastos_administracion', 'gastos_ventas_distribucion',
            'ingreso_financiero', 'otros_ingresos', 'ingresos_metodo_participacion',
            'gasto_financiero', 'intereses', 'otros_gastos', 'impuesto_ganancias', 'impuesto_renta',
        ];

        foreach ($anios as $anio) {
            $v = [];
            foreach ($conceptos as $concepto) {
                $v[$concepto] = $this->num($inputs[$concepto][$anio] ?? null);
            }
            foreach ($conceptos as $concepto) {
                $valores[$concepto][$anio] = $v[$concepto];
            }

            $bruta = $v['ingresos_ordinarios'] - $v['costo_ventas'];
            $operacional = $bruta - $v['gastos_administracion'] - $v['gastos_ventas_distribucion'];
            $antesImpuestos = $operacional + $v['ingreso_financiero'] + $v['otros_ingresos'] + $v['ingresos_metodo_participacion']
                - $v['gasto_financiero'] - $v['intereses'] - $v['otros_gastos'];
            $neta = $antesImpuestos - $v['impuesto_ganancias'] - $v['impuesto_renta'];

            $utilidadBruta[$anio] = $this->round($bruta);
            $utilidadOperacional[$anio] = $this->round($operacional);
            $gananciaAntesImpuestos[$anio] = $this->round($antesImpuestos);
            $utilidadNeta[$anio] = $this->round($neta);
        }

        $ingresosOrdinarios = $valores['ingresos_ordinarios'];
        $baseEstructural = array_merge($valores, [
            'utilidad_bruta' => $utilidadBruta,
            'utilidad_operacional' => $utilidadOperacional,
            'ganancia_antes_impuestos' => $gananciaAntesImpuestos,
            'utilidad_neta' => $utilidadNeta,
        ]);

        return [
            'anios' => $anios,
            'valores' => $valores,
            'utilidad_bruta' => $utilidadBruta,
            'utilidad_operacional' => $utilidadOperacional,
            'ganancia_antes_impuestos' => $gananciaAntesImpuestos,
            'utilidad_neta' => $utilidadNeta,
            'estructural' => $this->estructuralGrupo($baseEstructural, $ingresosOrdinarios, $anios),
            'variacion' => $this->variacionGrupo($baseEstructural, $anios),
        ];
    }

    /**
     * ORI — Otro Resultado Integral. Sin análisis estructural ni variación
     * (el prototipo solo muestra valor y total por año).
     */
    public function calcularOri(array $inputs, array $anios): array
    {
        $grupo = $this->sumarGrupo($inputs, self::ORI_CONCEPTOS, $anios);

        return [
            'anios' => $anios,
            'valores' => $grupo['valores'],
            'total_ori' => $grupo['subtotal'],
        ];
    }

    /**
     * Cartera — la provisión se registra en negativo y SÍ se incluye en
     * Cartera Neta (RN-14), pero se EXCLUYE de la composición del gráfico
     * de dona por ser un valor negativo (RN-13).
     */
    public function calcularCartera(array $inputs, array $anios): array
    {
        $conceptos = $this->conceptosConCustom($inputs, self::CARTERA_CONCEPTOS, 'cartera');
        $grupo = $this->sumarGrupo($inputs, $conceptos, $anios);
        $carteraNeta = $grupo['subtotal'];

        $composicion = [];
        foreach ($anios as $anio) {
            $totalPositivo = 0.0;
            foreach ($conceptos as $concepto) {
                if ($concepto === 'provision') {
                    continue;
                }
                $totalPositivo += $grupo['valores'][$concepto][$anio];
            }
            foreach ($conceptos as $concepto) {
                if ($concepto === 'provision') {
                    continue;
                }
                $composicion[$concepto][$anio] = $this->porcentajeONull($grupo['valores'][$concepto][$anio], $totalPositivo);
            }
        }

        return [
            'anios' => $anios,
            'valores' => $grupo['valores'],
            'cartera_neta' => $carteraNeta,
            'composicion_grafico' => $composicion,
            'variacion' => $this->variacionGrupo(array_merge($grupo['valores'], ['cartera_neta' => $carteraNeta]), $anios),
        ];
    }

    /**
     * Resumen — tarjetas del último año, validaciones automáticas
     * (FA-05/FA-06/CA-17). La tolerancia de la ecuación contable es un
     * parámetro configurable (Configuracion `ANALISIS_FINANCIERO_TOLERANCIA_DIFERENCIA_MM`),
     * nunca una constante hardcodeada — se lee y aplica en el controller,
     * este método solo expone la diferencia calculada.
     */
    public function calcularResumen(array $anios, array $activo, array $pasivo, array $patrimonio, array $utilidadNeta, array $cartera): array
    {
        $ultimoAnio = end($anios);
        $anioAnterior = $anios[count($anios) - 2] ?? null;

        $variacionUltimo = fn (array $serie) => $anioAnterior !== null
            ? $this->variacionRelativa($serie[$ultimoAnio] ?? 0.0, $serie[$anioAnterior] ?? 0.0)
            : null;

        return [
            'anio_resumen' => $ultimoAnio,
            'total_activo' => $activo['total_activo'][$ultimoAnio] ?? 0.0,
            'total_pasivo' => $pasivo['total_pasivo'][$ultimoAnio] ?? 0.0,
            'total_patrimonio' => $patrimonio['total_patrimonio'][$ultimoAnio] ?? 0.0,
            'utilidad_neta' => $utilidadNeta['utilidad_neta'][$ultimoAnio] ?? 0.0,
            'cartera_neta' => $cartera['cartera_neta'][$ultimoAnio] ?? 0.0,
            'variacion_total_activo' => $variacionUltimo($activo['total_activo']),
            'variacion_total_pasivo' => $variacionUltimo($pasivo['total_pasivo']),
            'variacion_total_patrimonio' => $variacionUltimo($patrimonio['total_patrimonio']),
            'variacion_utilidad_neta' => $variacionUltimo($utilidadNeta['utilidad_neta']),
            'variacion_cartera_neta' => $variacionUltimo($cartera['cartera_neta']),
            'diferencia_contable' => $patrimonio['validacion_contable'][$ultimoAnio] ?? 0.0,
        ];
    }

    /**
     * SCRUM-162 — fusiona la lista fija de conceptos de un grupo con las
     * filas custom que el usuario haya agregado ad-hoc en el formulario.
     *
     * Las filas custom son ad-hoc por informe (no un catálogo reutilizable):
     * viajan en la propia sección (`activo`, `pasivo`, `patrimonio`,
     * `cartera`) bajo la clave reservada `_custom`, como una lista de
     * `{grupo, clave, label}`. El VALOR de cada fila custom vive en el
     * mismo nivel que cualquier concepto fijo (`$inputs[$clave][$anio]`) —
     * así `sumarGrupo()` no necesita saber que una clave es custom, solo
     * necesita la lista completa de claves a sumar.
     *
     * Una clave custom nunca puede pisar una clave fija del grupo (se
     * ignora silenciosamente si coincide) y las claves duplicadas se
     * deduplican.
     */
    private function conceptosConCustom(array $inputs, array $conceptosFijos, string $grupo): array
    {
        $custom = $inputs['_custom'] ?? [];
        if (!is_array($custom)) {
            return $conceptosFijos;
        }

        $customClaves = [];
        foreach ($custom as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            if (($fila['grupo'] ?? null) !== $grupo) {
                continue;
            }
            $clave = $fila['clave'] ?? null;
            if (!is_string($clave) || $clave === '' || in_array($clave, $conceptosFijos, true)) {
                continue;
            }
            $customClaves[] = $clave;
        }

        return array_merge($conceptosFijos, array_values(array_unique($customClaves)));
    }

    /**
     * Suma un conjunto de conceptos por año (subtotal de grupo).
     */
    private function sumarGrupo(array $inputs, array $conceptos, array $anios): array
    {
        $valores = [];
        $subtotal = array_fill_keys($anios, 0.0);

        foreach ($conceptos as $concepto) {
            foreach ($anios as $anio) {
                $valor = $this->num($inputs[$concepto][$anio] ?? null);
                $valores[$concepto][$anio] = $valor;
                $subtotal[$anio] += $valor;
            }
        }

        foreach ($anios as $anio) {
            $subtotal[$anio] = $this->round($subtotal[$anio]);
        }

        return ['valores' => $valores, 'subtotal' => $subtotal];
    }

    private function sumarSubtotales(array $subtotales, array $anios): array
    {
        $total = array_fill_keys($anios, 0.0);
        foreach ($subtotales as $subtotal) {
            foreach ($anios as $anio) {
                $total[$anio] += $subtotal[$anio] ?? 0.0;
            }
        }
        foreach ($anios as $anio) {
            $total[$anio] = $this->round($total[$anio]);
        }
        return $total;
    }

    /**
     * Análisis estructural: valor del concepto / base del mismo año.
     * `$valoresPorConcepto` puede incluir tanto conceptos individuales como
     * subtotales de grupo (mismo cálculo para ambos).
     */
    private function estructuralGrupo(array $valoresPorConcepto, array $base, array $anios): array
    {
        $resultado = [];
        foreach ($valoresPorConcepto as $concepto => $porAnio) {
            foreach ($anios as $anio) {
                $resultado[$concepto][$anio] = $this->porcentajeONull($porAnio[$anio] ?? 0.0, $base[$anio] ?? 0.0);
            }
        }
        return $resultado;
    }

    /**
     * Variación relativa entre años consecutivos — el primer año de la
     * serie no tiene variación (no hay año anterior dentro del análisis).
     */
    private function variacionGrupo(array $valoresPorConcepto, array $anios): array
    {
        $resultado = [];
        foreach ($valoresPorConcepto as $concepto => $porAnio) {
            foreach ($anios as $index => $anio) {
                if ($index === 0) {
                    continue;
                }
                $anioAnterior = $anios[$index - 1];
                $resultado[$concepto][$anio] = $this->variacionRelativa($porAnio[$anio] ?? 0.0, $porAnio[$anioAnterior] ?? 0.0);
            }
        }
        return $resultado;
    }

    private function variacionRelativa(float $actual, float $anterior): ?float
    {
        if ($anterior == 0.0) {
            return null; // N/A — RF-12/CA-14/FA-07
        }
        return $this->round(($actual - $anterior) / $anterior, 4);
    }

    private function porcentajeONull(float $numerador, float $denominador): ?float
    {
        if ($denominador == 0.0) {
            return null; // N/A — RF-12/CA-14/FA-07
        }
        return $this->round($numerador / $denominador, 4);
    }

    private function num(mixed $value, float $default = 0.0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return (float) $value;
    }

    private function round(float $value, int $decimals = 2): float
    {
        return round($value, $decimals);
    }
}
