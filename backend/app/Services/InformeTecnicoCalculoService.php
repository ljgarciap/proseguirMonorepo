<?php

namespace App\Services;

/**
 * Replica las fórmulas del Excel de referencia de SCRUM-120 Fase 2
 * ("INFORME TÉCNICO - ENTRE VERDE M+D.xlsx", código interno CR-RO-09A v5,
 * hoja "ENTREVERDE"). Cada método corresponde 1:1 a una sección del
 * documento de diseño (docs/designs/scrum-120-fase2-formulas-y-fidelidad-prototipo.md)
 * y fue verificado contra los valores reales del fixture de prueba
 * (tests/Fixtures/informe-tecnico-entre-verde.xlsx) con PhpSpreadsheet.
 *
 * Contrato: el frontend manda solo los inputs crudos de cada sección;
 * este servicio siempre recalcula los totales/porcentajes antes de
 * persistir — nunca se confía en un total calculado por el cliente.
 *
 * SCRUM-186 (segunda entrega, 2026-08-06): dos particularidades del Excel
 * original que antes se replicaban tal cual quedaron reemplazadas por
 * requerimiento explícito del negocio:
 *  - calcularCostos(): Honorarios y Financieros ahora SÍ suman al total.
 *  - calcularCoberturas(): "Cobertura de Garantía" usa el total de TODAS
 *    las unidades vendidas (no solo Apartamentos) como denominador —
 *    Apartamentos daba 0 en proyectos que venden casas/lotes/locales.
 */
class InformeTecnicoCalculoService
{
    /**
     * Sección Ingeniero — Ventas Totales Proyecto (celdas E7:F14 del Excel).
     *
     * SCRUM-162: además de los 7 campos fijos, suma las filas custom ad-hoc
     * que el usuario haya agregado en el formulario (lista `_custom` del
     * mismo payload, `{clave, label, valor}` — ver `filasCustom()`). Son
     * ad-hoc por informe, no un catálogo reutilizable entre expedientes.
     */
    public function calcularVentasTotalesProyecto(array $inputs): array
    {
        $casas = $this->num($inputs, 'casas');
        $apartamentos = $this->num($inputs, 'apartamentos');
        $parqueaderos = $this->num($inputs, 'parqueaderos');
        $conexionGasArras = $this->num($inputs, 'conexion_gas_arras');
        $localComercial = $this->num($inputs, 'local_comercial');
        $cuartosUtiles = $this->num($inputs, 'cuartos_utiles');
        $otros = $this->num($inputs, 'otros');
        $custom = $this->filasCustom($inputs);
        $totalCustom = array_sum(array_column($custom, 'valor'));

        $totalVentas = $casas + $apartamentos + $parqueaderos + $conexionGasArras
            + $localComercial + $cuartosUtiles + $otros + $totalCustom;

        $porcentajes = [
            'casas' => $this->porcentaje($casas, $totalVentas),
            'apartamentos' => $this->porcentaje($apartamentos, $totalVentas),
            'parqueaderos' => $this->porcentaje($parqueaderos, $totalVentas),
            'conexion_gas_arras' => $this->porcentaje($conexionGasArras, $totalVentas),
            'local_comercial' => $this->porcentaje($localComercial, $totalVentas),
            'cuartos_utiles' => $this->porcentaje($cuartosUtiles, $totalVentas),
            'otros' => $this->porcentaje($otros, $totalVentas),
        ];
        foreach ($custom as $fila) {
            $porcentajes[$fila['clave']] = $this->porcentaje($fila['valor'], $totalVentas);
        }

        return [
            'casas' => $casas,
            'apartamentos' => $apartamentos,
            'parqueaderos' => $parqueaderos,
            'conexion_gas_arras' => $conexionGasArras,
            'local_comercial' => $localComercial,
            'cuartos_utiles' => $cuartosUtiles,
            'otros' => $otros,
            'custom' => $custom,
            'total_ventas' => $this->round($totalVentas),
            'porcentajes' => $porcentajes,
        ];
    }

    /**
     * Sección Ingeniero — Costos (celdas E15:H23).
     *
     * SCRUM-186 (segunda entrega): Honorarios (E20) y Financieros (E23)
     * ahora suman al total de Costos — el Excel de referencia original los
     * excluía (E15 = E16+E17+E18+E19+E22), pero el negocio pidió
     * explícitamente incluirlos en la segunda entrega del proceso.
     *
     * SCRUM-162: las filas custom ad-hoc siempre suman al total, igual que
     * Lote/Directos/Directos Urbanismo/Indirectos/Incremento en costos.
     */
    public function calcularCostos(array $inputs, float $totalVentas): array
    {
        $lote = $this->num($inputs, 'lote');
        $directos = $this->num($inputs, 'directos');
        $directosUrbanismo = $this->num($inputs, 'directos_urbanismo');
        $indirectos = $this->num($inputs, 'indirectos');
        $honorarios = $this->num($inputs, 'honorarios');
        $incrementoCostos = $this->num($inputs, 'incremento_costos');
        $financieros = $this->num($inputs, 'financieros');
        $custom = $this->filasCustom($inputs);
        $totalCustom = array_sum(array_column($custom, 'valor'));

        $totalCostos = $lote + $directos + $directosUrbanismo + $indirectos + $honorarios + $incrementoCostos + $financieros + $totalCustom;

        return [
            'lote' => $lote,
            'directos' => $directos,
            'directos_urbanismo' => $directosUrbanismo,
            'indirectos' => $indirectos,
            'honorarios' => $honorarios,
            'incremento_costos' => $incrementoCostos,
            'financieros' => $financieros,
            'custom' => $custom,
            'total_costos' => $this->round($totalCostos),
            'porcentaje_costos' => $this->porcentaje($totalCostos, $totalVentas),
        ];
    }

    /**
     * Sección Ingeniero — Invertido (celdas E25:E34).
     *
     * SCRUM-186 (segunda entrega): agrega "Cuotas Iniciales en Fiducia"
     * debajo de Costos Indirectos, sumando también al Total Invertido.
     */
    public function calcularInvertido(array $inputs, float $totalVentas): array
    {
        $lote = $this->num($inputs, 'lote');
        $costosDirectos = $this->num($inputs, 'costos_directos');
        $costosIndirectos = $this->num($inputs, 'costos_indirectos');
        $cuotasInicialesFiducia = $this->num($inputs, 'cuotas_iniciales_fiducia');
        $recursosPropios = $this->num($inputs, 'recursos_propios');
        $cuotasInicialesYaPagadas = $this->num($inputs, 'cuotas_iniciales_ya_pagadas');

        $totalInvertido = $lote + $costosDirectos + $costosIndirectos + $cuotasInicialesFiducia;
        $totalFuentes = $recursosPropios + $cuotasInicialesYaPagadas;

        return [
            'lote' => $lote,
            'costos_directos' => $costosDirectos,
            'costos_indirectos' => $costosIndirectos,
            'cuotas_iniciales_fiducia' => $cuotasInicialesFiducia,
            'total_invertido' => $this->round($totalInvertido),
            'porcentaje_invertido' => $this->porcentaje($totalInvertido, $totalVentas),
            'recursos_propios' => $recursosPropios,
            'cuotas_iniciales_ya_pagadas' => $cuotasInicialesYaPagadas,
            'total_fuentes' => $this->round($totalFuentes),
        ];
    }

    /**
     * Sección Coordinador — Crédito Solicitado (celdas E36:E41).
     * $cuotasInicialesYaPagadas viene de Invertido.cuotas_iniciales_ya_pagadas
     * (Ingeniero) — no se le vuelve a pedir al Coordinador (E39 = +E33).
     */
    public function calcularCreditoSolicitado(array $inputs, float $totalVentas, float $cuotasInicialesYaPagadas): array
    {
        $creditoSolicitado = $this->num($inputs, 'credito_solicitado');
        $aptosVendidos = $this->num($inputs, 'aptos_vendidos');
        // Editable, no constante fija — 30% es solo el valor de ejemplo del Excel (E40).
        $porcentajeCuotasInicialesPendientes = $this->num($inputs, 'porcentaje_cuotas_iniciales_pendientes', 0.30);

        $cuotasInicialesPendientes = ($aptosVendidos * $porcentajeCuotasInicialesPendientes) - $cuotasInicialesYaPagadas;
        $saldoRecaudarContraentregaVendidos = $aptosVendidos - $cuotasInicialesYaPagadas - $cuotasInicialesPendientes;

        return [
            'credito_solicitado' => $creditoSolicitado,
            'porcentaje_sobre_ventas' => $this->porcentaje($creditoSolicitado, $totalVentas),
            'aptos_vendidos' => $aptosVendidos,
            'porcentaje_aptos_vendidos_sobre_ventas' => $this->porcentaje($aptosVendidos, $totalVentas),
            'cuotas_iniciales_ya_pagadas' => $cuotasInicialesYaPagadas,
            'porcentaje_cuotas_iniciales_pendientes' => $porcentajeCuotasInicialesPendientes,
            'cuotas_iniciales_pendientes' => $this->round($cuotasInicialesPendientes),
            'saldo_recaudar_contraentrega_vendidos' => $this->round($saldoRecaudarContraentregaVendidos),
        ];
    }

    /**
     * Sección Coordinador — Saldo por Recaudar Contraentrega "por vender"
     * (celdas E42:E48). $creditoSolicitadoCalculado es la sección ya
     * calculada por calcularCreditoSolicitado() en el mismo request —
     * total_pendiente_por_recaudar replica tal cual la fórmula real del
     * Excel (E48 = E40+E41+E42), aunque a simple vista uno esperaría
     * E41+E45 — así está confirmado con Luis, no se "corrige".
     */
    public function calcularSaldosPorRecaudarContraentrega(
        array $inputs,
        float $totalVentas,
        float $aptosVendidos,
        array $creditoSolicitadoCalculado
    ): array {
        // Editable, no constante fija — 10% es solo el valor de ejemplo del Excel (E43).
        $porcentajeCuotasIniciales = $this->num($inputs, 'porcentaje_cuotas_iniciales', 0.10);
        // Sin fórmula fija visible en el Excel (E44 vacío en el ejemplo) — input editable.
        $cuotasInicialesPendientes = $this->num($inputs, 'cuotas_iniciales_pendientes');

        $aptosXVender = $totalVentas - $aptosVendidos;
        $cuotasIniciales = $aptosXVender * $porcentajeCuotasIniciales;
        $saldoRecaudarContraentregaPorVender = $aptosXVender - $cuotasIniciales - $cuotasInicialesPendientes;

        $cuotasInicialesPendientesVendidos = $creditoSolicitadoCalculado['cuotas_iniciales_pendientes'] ?? 0;
        $saldoRecaudarContraentregaVendidos = $creditoSolicitadoCalculado['saldo_recaudar_contraentrega_vendidos'] ?? 0;
        $totalPendientePorRecaudar = $cuotasInicialesPendientesVendidos + $saldoRecaudarContraentregaVendidos + $aptosXVender;

        return [
            'aptos_x_vender' => $this->round($aptosXVender),
            'porcentaje_sobre_ventas' => $this->porcentaje($aptosXVender, $totalVentas),
            'porcentaje_cuotas_iniciales' => $porcentajeCuotasIniciales,
            'cuotas_iniciales' => $this->round($cuotasIniciales),
            'cuotas_iniciales_pendientes' => $cuotasInicialesPendientes,
            'saldo_recaudar_contraentrega_por_vender' => $this->round($saldoRecaudarContraentregaPorVender),
            'total_pendiente_por_recaudar' => $this->round($totalPendientePorRecaudar),
        ];
    }

    /**
     * Análisis de Financiación (celdas E52:E59) — 100% derivado, sin
     * inputs directos del Coordinador. $costos e $invertido son las
     * secciones ya calculadas del Ingeniero (persistidas previamente).
     */
    public function calcularAnalisisFinanciacion(
        array $costos,
        array $invertido,
        array $creditoSolicitado,
        array $saldosPorRecaudar
    ): array {
        $costoObra = $costos['total_costos'] ?? 0;
        $valorInvertido = $invertido['total_invertido'] ?? 0;
        $credito = $creditoSolicitado['credito_solicitado'] ?? 0;
        $cuotasInicialesXRecaudar = $creditoSolicitado['cuotas_iniciales_pendientes'] ?? 0;
        // E58 = +E43 ("Cuotas Iniciales" por vender, no "Cuotas Iniciales Pendientes" por vender).
        $cuotasInicialesPendientesPorVender = $saldosPorRecaudar['cuotas_iniciales'] ?? 0;

        $saldoXFinanciarBruto = $costoObra - $valorInvertido - $credito;
        $saldoXFinanciar = $saldoXFinanciarBruto - $cuotasInicialesXRecaudar;
        $saldoNoFinanciado = $saldoXFinanciar - $cuotasInicialesPendientesPorVender;

        return [
            'costo_obra' => $this->round($costoObra),
            'valor_invertido' => $this->round($valorInvertido),
            'credito' => $this->round($credito),
            'saldo_x_financiar_bruto' => $this->round($saldoXFinanciarBruto),
            'cuotas_iniciales_x_recaudar' => $this->round($cuotasInicialesXRecaudar),
            'saldo_x_financiar' => $this->round($saldoXFinanciar),
            'cuotas_iniciales_pendientes_por_vender' => $this->round($cuotasInicialesPendientesPorVender),
            'saldo_no_financiado' => $this->round($saldoNoFinanciado),
        ];
    }

    /**
     * Coberturas (celdas F66:G78) — 100% derivado. Semáforo simple
     * rojo/verde (no el conditional-formatting completo del Excel).
     *
     * Los umbrales usados son los de la tabla "Valores Máximos" del propio
     * Excel (celdas B77:B79 = "A) 100%", "B) 70%", "C) 60%"), verificados
     * con PhpSpreadsheet contra el fixture — nótese que estos difieren de
     * los umbrales sugeridos en la primera tabla del documento de diseño
     * (A: 0.7, B/C: 0.6). Se priorizaron los valores literales del Excel
     * por ser la fuente de verdad más primaria; documentado como decisión
     * de diseño explícita, no una suposición (ver reporte de la tarea).
     */
    public function calcularCoberturas(
        array $creditoSolicitado,
        array $analisisFinanciacion,
        array $saldosPorRecaudar,
        array $ventasTotalesProyecto
    ): array {
        $credito = $creditoSolicitado['credito_solicitado'] ?? 0;
        $saldoXFinanciar = $analisisFinanciacion['saldo_x_financiar'] ?? 0;
        $saldoNoFinanciado = $analisisFinanciacion['saldo_no_financiado'] ?? 0;
        $saldoContraentregaVendidos = $creditoSolicitado['saldo_recaudar_contraentrega_vendidos'] ?? 0;
        $saldoContraentregaPorVender = $saldosPorRecaudar['saldo_recaudar_contraentrega_por_vender'] ?? 0;
        // SCRUM-186 (segunda entrega): el denominador de "Cobertura de
        // Garantía" pasa a ser el total de TODAS las unidades vendidas
        // (total_ventas: apartamentos + casas + parqueaderos + demás
        // conceptos fijos + filas custom). Antes usaba solo "Apartamentos"
        // (E9), que daba 0 — y por lo tanto una cobertura siempre en 0% —
        // en cualquier proyecto que no vendiera literalmente apartamentos
        // (casas, lotes, locales), aunque el proyecto sí tuviera ventas.
        $totalUnidadesVendidas = $ventasTotalesProyecto['total_ventas'] ?? 0;

        $peorNumerador = $credito + $saldoXFinanciar;
        $peorDenominador = $saldoContraentregaVendidos;
        $peorCobertura = $this->porcentaje($peorNumerador, $peorDenominador);

        $mejorNumerador = $credito + $saldoNoFinanciado;
        $mejorDenominador = $saldoContraentregaVendidos + $saldoContraentregaPorVender;
        $mejorCobertura = $this->porcentaje($mejorNumerador, $mejorDenominador);

        $garantiaCobertura = $this->porcentaje($credito, $totalUnidadesVendidas);

        return [
            'peor_escenario' => [
                'numerador' => $this->round($peorNumerador),
                'denominador' => $this->round($peorDenominador),
                'cobertura' => $peorCobertura,
                'umbral_maximo' => 1.0,
                'semaforo' => $peorCobertura > 1.0 ? 'rojo' : 'verde',
            ],
            'mejor_escenario' => [
                'numerador' => $this->round($mejorNumerador),
                'denominador' => $this->round($mejorDenominador),
                'cobertura' => $mejorCobertura,
                'umbral_maximo' => 0.7,
                'semaforo' => $mejorCobertura > 0.7 ? 'rojo' : 'verde',
            ],
            'cobertura_garantia' => [
                'numerador' => $this->round($credito),
                'denominador' => $this->round($totalUnidadesVendidas),
                'cobertura' => $garantiaCobertura,
                'umbral_maximo' => 0.6,
                'semaforo' => $garantiaCobertura > 0.6 ? 'rojo' : 'verde',
            ],
        ];
    }

    /**
     * SCRUM-162 — extrae las filas custom ad-hoc de un payload de sección
     * (Ventas Totales Proyecto o Costos). Vienen bajo la clave reservada
     * `_custom` como lista de `{clave, label, valor}` — a diferencia de
     * Análisis Financiero (multi-año, valor vive en `inputs[clave][anio]`),
     * Informe Técnico es un punto en el tiempo: el valor de la fila custom
     * viaja directo en el propio objeto `_custom`, no en `inputs[clave]`.
     */
    private function filasCustom(array $inputs): array
    {
        $lista = $inputs['_custom'] ?? [];
        if (!is_array($lista)) {
            return [];
        }

        $resultado = [];
        $clavesVistas = [];
        foreach ($lista as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $clave = $fila['clave'] ?? null;
            if (!is_string($clave) || $clave === '' || isset($clavesVistas[$clave])) {
                continue;
            }
            $clavesVistas[$clave] = true;
            $resultado[] = [
                'clave' => $clave,
                'label' => (string) ($fila['label'] ?? $clave),
                'valor' => $this->num($fila, 'valor'),
            ];
        }
        return $resultado;
    }

    private function num(array $inputs, string $key, float $default = 0.0): float
    {
        $value = $inputs[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return (float) $value;
    }

    private function porcentaje(float $numerador, float $denominador): float
    {
        if ($denominador == 0.0) {
            return 0.0;
        }
        return $this->round($numerador / $denominador, 10);
    }

    private function round(float $value, int $decimals = 2): float
    {
        return round($value, $decimals);
    }
}
