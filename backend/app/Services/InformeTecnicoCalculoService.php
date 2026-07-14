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
 * Dos particularidades del Excel real se replican tal cual (confirmado
 * con Luis, no son bugs a "arreglar"):
 *  - calcularCostos(): el total NO suma Honorarios ni Financieros.
 *  - calcularCoberturas(): "Cobertura de Garantía" usa Apartamentos
 *    (no el total de ventas) como denominador de referencia.
 */
class InformeTecnicoCalculoService
{
    /**
     * Sección Ingeniero — Ventas Totales Proyecto (celdas E7:F14 del Excel).
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

        $totalVentas = $casas + $apartamentos + $parqueaderos + $conexionGasArras
            + $localComercial + $cuartosUtiles + $otros;

        return [
            'casas' => $casas,
            'apartamentos' => $apartamentos,
            'parqueaderos' => $parqueaderos,
            'conexion_gas_arras' => $conexionGasArras,
            'local_comercial' => $localComercial,
            'cuartos_utiles' => $cuartosUtiles,
            'otros' => $otros,
            'total_ventas' => $this->round($totalVentas),
            'porcentajes' => [
                'casas' => $this->porcentaje($casas, $totalVentas),
                'apartamentos' => $this->porcentaje($apartamentos, $totalVentas),
                'parqueaderos' => $this->porcentaje($parqueaderos, $totalVentas),
                'conexion_gas_arras' => $this->porcentaje($conexionGasArras, $totalVentas),
                'local_comercial' => $this->porcentaje($localComercial, $totalVentas),
                'cuartos_utiles' => $this->porcentaje($cuartosUtiles, $totalVentas),
                'otros' => $this->porcentaje($otros, $totalVentas),
            ],
        ];
    }

    /**
     * Sección Ingeniero — Costos (celdas E15:H23). E15 = E16+E17+E18+E19+E22,
     * es decir NO suma Honorarios (E20) ni Financieros (E23) pese al título
     * "Costos (Incluido Costo Financiero)" — así está en el documento de
     * control interno vigente, confirmado con Luis.
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

        $totalCostos = $lote + $directos + $directosUrbanismo + $indirectos + $incrementoCostos;

        return [
            'lote' => $lote,
            'directos' => $directos,
            'directos_urbanismo' => $directosUrbanismo,
            'indirectos' => $indirectos,
            'honorarios' => $honorarios,
            'incremento_costos' => $incrementoCostos,
            'financieros' => $financieros,
            'total_costos' => $this->round($totalCostos),
            'porcentaje_costos' => $this->porcentaje($totalCostos, $totalVentas),
            'nota' => 'Honorarios y Financieros no se incluyen en el total de Costos (regla del documento de control CR-RO-09A).',
        ];
    }

    /**
     * Sección Ingeniero — Invertido (celdas E25:E34).
     */
    public function calcularInvertido(array $inputs, float $totalVentas): array
    {
        $lote = $this->num($inputs, 'lote');
        $costosDirectos = $this->num($inputs, 'costos_directos');
        $costosIndirectos = $this->num($inputs, 'costos_indirectos');
        $recursosPropios = $this->num($inputs, 'recursos_propios');
        $cuotasInicialesYaPagadas = $this->num($inputs, 'cuotas_iniciales_ya_pagadas');

        $totalInvertido = $lote + $costosDirectos + $costosIndirectos;
        $totalFuentes = $recursosPropios + $cuotasInicialesYaPagadas;

        return [
            'lote' => $lote,
            'costos_directos' => $costosDirectos,
            'costos_indirectos' => $costosIndirectos,
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
    public function calcularCoberturas(array $creditoSolicitado, array $analisisFinanciacion, array $saldosPorRecaudar): array
    {
        $credito = $creditoSolicitado['credito_solicitado'] ?? 0;
        $saldoXFinanciar = $analisisFinanciacion['saldo_x_financiar'] ?? 0;
        $saldoNoFinanciado = $analisisFinanciacion['saldo_no_financiado'] ?? 0;
        $saldoContraentregaVendidos = $creditoSolicitado['saldo_recaudar_contraentrega_vendidos'] ?? 0;
        $saldoContraentregaPorVender = $saldosPorRecaudar['saldo_recaudar_contraentrega_por_vender'] ?? 0;
        // "Valor Total en Ventas" en Cobertura de Garantía = Apartamentos (E9), no el total de ventas (E7).
        $apartamentos = $creditoSolicitado['aptos_vendidos'] ?? 0;

        $peorNumerador = $credito + $saldoXFinanciar;
        $peorDenominador = $saldoContraentregaVendidos;
        $peorCobertura = $this->porcentaje($peorNumerador, $peorDenominador);

        $mejorNumerador = $credito + $saldoNoFinanciado;
        $mejorDenominador = $saldoContraentregaVendidos + $saldoContraentregaPorVender;
        $mejorCobertura = $this->porcentaje($mejorNumerador, $mejorDenominador);

        $garantiaCobertura = $this->porcentaje($credito, $apartamentos);

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
                'denominador' => $this->round($apartamentos),
                'cobertura' => $garantiaCobertura,
                'umbral_maximo' => 0.6,
                'semaforo' => $garantiaCobertura > 0.6 ? 'rojo' : 'verde',
            ],
        ];
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
