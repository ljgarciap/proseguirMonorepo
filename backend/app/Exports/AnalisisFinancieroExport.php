<?php

namespace App\Exports;

use App\Models\AnalisisFinanciero;
use App\Models\CreditoOrdinario;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Documento consolidado del Análisis Financiero (SCRUM-155): encabezado,
 * 6 pestañas con valor + análisis estructural + variación relativa por año,
 * y resumen con validaciones. Marca "BORRADOR" si el análisis todavía no
 * está `confirmado` (mismo patrón que InformeTecnicoExport).
 */
class AnalisisFinancieroExport implements FromArray, WithStyles, WithTitle, ShouldAutoSize
{
    public const LABELS_ACTIVO = [
        'caja_bancos' => 'Caja y bancos', 'cxc_comerciales' => 'Cuentas por cobrar comerciales',
        'cxc_vinculados_economicos' => 'CXC vinculados económicos', 'impuestos_corrientes' => 'Impuestos corrientes',
        'otras_cxc' => 'Otras cuentas por cobrar', 'inventarios' => 'Inventarios',
        'gastos_pagados_anticipado' => 'Gastos pagados por anticipado',
        'propiedad_planta_equipo' => 'Propiedad, planta y equipo', 'construcciones_en_curso' => 'Construcciones en curso',
        'inversiones_subsidiarias' => 'Inversiones en subsidiarias', 'otras_cxc_no_corrientes' => 'Otras cuentas por cobrar no corrientes',
        'inversion_proyectos_solares' => 'Inversión en proyectos solares', 'otros_activos_no_financieros' => 'Otros activos no financieros',
        'impuesto_diferido' => 'Impuesto diferido', 'gastos_pagados_anticipado_nc' => 'Gastos pagados por anticipado (no corriente)',
        'inversion_posicionamiento_marca' => 'Inversión en posicionamiento de marca', 'valorizaciones' => 'Valorizaciones',
        'inversiones_sociedades' => 'Inversiones en sociedades', 'cxc_vinculados_economicos_nc' => 'CXC vinculados económicos (no corriente)',
        'impuesto_renta_activo' => 'Impuesto a la renta', 'otros_activos_no_corrientes' => 'Otros activos no corrientes',
    ];

    public const LABELS_PASIVO = [
        'obligaciones_financieras' => 'Obligaciones financieras', 'obligaciones_particulares' => 'Obligaciones con particulares',
        'proveedores' => 'Proveedores', 'otras_cxp' => 'Otras cuentas por pagar',
        'impuestos_corrientes_pasivo' => 'Impuestos corrientes', 'beneficios_empleados' => 'Beneficios a empleados',
        'otros_pasivos' => 'Otros pasivos', 'pasivos_estimados_provisiones' => 'Pasivos estimados y provisiones',
        'obligaciones_financieras_lp' => 'Obligaciones financieras de largo plazo',
        'obligaciones_vinculados_lp' => 'Obligaciones con vinculados de largo plazo',
        'impuesto_diferido_pasivo' => 'Impuesto diferido',
    ];

    public const LABELS_PATRIMONIO = [
        'capital_suscrito_pagado' => 'Capital suscrito y pagado', 'reservas' => 'Reservas',
        'superavit_revaluacion_activos' => 'Superávit por revaluación de activos',
        'resultados_acumulados' => 'Resultados acumulados', 'resultados_acumulados_niif' => 'Resultados acumulados por NIIF',
        'resultados_ejercicio' => 'Resultados del ejercicio', 'ori_conversion_moneda' => 'ORI - Conversión de moneda extranjera',
    ];

    public const LABELS_CARTERA = [
        'cartera_corriente' => 'Cartera corriente', 'vencida_0_60' => 'Vencida entre 0 y 60 días',
        'vencida_mas_60' => 'Vencida con más de 60 días', 'dificil_cobro' => 'Deudas de difícil cobro',
        'provision' => 'Provisión de cartera',
    ];

    private array $boldRows = [];

    public function __construct(
        private CreditoOrdinario $credito,
        private AnalisisFinanciero $analisis,
        private array $calculado
    ) {
    }

    public function title(): string
    {
        return 'Analisis Financiero';
    }

    public function array(): array
    {
        $rows = [];
        $bold = [];
        $anios = $this->calculado['anios'];

        $addBold = function (array $row) use (&$rows, &$bold) {
            $bold[] = count($rows) + 1;
            $rows[] = $row;
        };
        $add = function (array $row) use (&$rows) {
            $rows[] = $row;
        };

        $solicitudCredito = $this->credito->solicitudCredito;
        $cliente = $solicitudCredito?->cliente;

        $addBold(['ANÁLISIS FINANCIERO']);
        if ($this->analisis->estado !== 'confirmado') {
            $addBold(['*** BORRADOR — ANÁLISIS NO CONFIRMADO ***']);
        }
        $add([]);
        $add(['No. de Solicitud', $this->credito->numero_solicitud]);
        $add(['Cliente', $cliente?->nombre ?? '—']);
        $add(['Identificación', $cliente?->numero_documento ?? '—']);
        $add(['Monto Solicitado', $solicitudCredito?->monto_solicitado ?? 0]);
        $add(['Años del análisis', implode(' - ', $anios)]);
        $add(['Estado', $this->analisis->estado]);
        $add([]);

        $addBold(['ACTIVO']);
        $add($this->encabezado($anios));
        foreach (self::LABELS_ACTIVO as $clave => $label) {
            $add($this->filaConcepto($label, $clave, $this->calculado['activo'], $anios));
        }
        $addBold($this->filaConcepto('TOTAL ACTIVO CORRIENTE', 'activo_corriente', $this->calculado['activo'], $anios, 'total_activo_corriente'));
        $addBold($this->filaConcepto('TOTAL ACTIVO NO CORRIENTE', 'activo_no_corriente', $this->calculado['activo'], $anios, 'total_activo_no_corriente'));
        $addBold(['TOTAL ACTIVO', ...$this->valoresPorAnio($this->calculado['activo']['total_activo'], $anios)]);
        $add([]);

        $addBold(['PASIVO']);
        $add($this->encabezado($anios));
        foreach (self::LABELS_PASIVO as $clave => $label) {
            $add($this->filaConcepto($label, $clave, $this->calculado['pasivo'], $anios));
        }
        $addBold($this->filaConcepto('TOTAL PASIVO CORRIENTE', 'pasivo_corriente', $this->calculado['pasivo'], $anios, 'total_pasivo_corriente'));
        $addBold($this->filaConcepto('TOTAL PASIVO NO CORRIENTE', 'pasivo_no_corriente', $this->calculado['pasivo'], $anios, 'total_pasivo_no_corriente'));
        $addBold(['TOTAL PASIVO', ...$this->valoresPorAnio($this->calculado['pasivo']['total_pasivo'], $anios)]);
        $add([]);

        $addBold(['PATRIMONIO']);
        $add($this->encabezado($anios));
        foreach (self::LABELS_PATRIMONIO as $clave => $label) {
            $add($this->filaConcepto($label, $clave, $this->calculado['patrimonio'], $anios));
        }
        $addBold(['TOTAL PATRIMONIO', ...$this->valoresPorAnio($this->calculado['patrimonio']['total_patrimonio'], $anios)]);
        $add(['TOTAL PASIVO + PATRIMONIO', ...$this->valoresPorAnio($this->calculado['patrimonio']['total_pasivo_mas_patrimonio'], $anios)]);
        $add(['Diferencia (validación contable)', ...$this->valoresPorAnio($this->calculado['patrimonio']['validacion_contable'], $anios)]);
        $add([]);

        $un = $this->calculado['utilidad_neta'];
        $addBold(['UTILIDAD NETA']);
        $add($this->encabezado($anios));
        $add(['Ingresos ordinarios', ...$this->valoresPorAnio($un['valores']['ingresos_ordinarios'], $anios)]);
        $add(['Costo de ventas', ...$this->valoresPorAnio($un['valores']['costo_ventas'], $anios)]);
        $addBold(['UTILIDAD BRUTA', ...$this->valoresPorAnio($un['utilidad_bruta'], $anios)]);
        $add(['Gastos de administración', ...$this->valoresPorAnio($un['valores']['gastos_administracion'], $anios)]);
        $add(['Gastos de ventas y distribución', ...$this->valoresPorAnio($un['valores']['gastos_ventas_distribucion'], $anios)]);
        $addBold(['UTILIDAD OPERACIONAL', ...$this->valoresPorAnio($un['utilidad_operacional'], $anios)]);
        $add(['Ingreso financiero', ...$this->valoresPorAnio($un['valores']['ingreso_financiero'], $anios)]);
        $add(['Otros ingresos', ...$this->valoresPorAnio($un['valores']['otros_ingresos'], $anios)]);
        $add(['Ingresos método de participación', ...$this->valoresPorAnio($un['valores']['ingresos_metodo_participacion'], $anios)]);
        $add(['Gasto financiero', ...$this->valoresPorAnio($un['valores']['gasto_financiero'], $anios)]);
        $add(['Intereses', ...$this->valoresPorAnio($un['valores']['intereses'], $anios)]);
        $add(['Otros gastos', ...$this->valoresPorAnio($un['valores']['otros_gastos'], $anios)]);
        $addBold(['GANANCIA ANTES DE IMPUESTOS', ...$this->valoresPorAnio($un['ganancia_antes_impuestos'], $anios)]);
        $add(['Impuesto a las ganancias', ...$this->valoresPorAnio($un['valores']['impuesto_ganancias'], $anios)]);
        $add(['Impuesto de renta', ...$this->valoresPorAnio($un['valores']['impuesto_renta'], $anios)]);
        $addBold(['UTILIDAD NETA', ...$this->valoresPorAnio($un['utilidad_neta'], $anios)]);
        $add([]);

        $ori = $this->calculado['ori'];
        $addBold(['ORI - OTRO RESULTADO INTEGRAL']);
        $add(['Concepto', ...$anios]);
        $add(['Superávit por revaluación de activos', ...$this->valoresPorAnio($ori['valores']['superavit_revaluacion_activos'], $anios)]);
        $add(['Conversión moneda extranjera', ...$this->valoresPorAnio($ori['valores']['conversion_moneda_extranjera'], $anios)]);
        $addBold(['TOTAL ORI', ...$this->valoresPorAnio($ori['total_ori'], $anios)]);
        $add([]);

        $cartera = $this->calculado['cartera'];
        $addBold(['CARTERA']);
        $add(['Concepto', ...$anios]);
        foreach (self::LABELS_CARTERA as $clave => $label) {
            $add([$label, ...$this->valoresPorAnio($cartera['valores'][$clave], $anios)]);
        }
        $addBold(['CARTERA NETA', ...$this->valoresPorAnio($cartera['cartera_neta'], $anios)]);
        $add([]);

        $resumen = $this->calculado['resumen'];
        $addBold(['RESUMEN — Año ' . $resumen['anio_resumen']]);
        $add(['Total Activo', $resumen['total_activo']]);
        $add(['Total Pasivo', $resumen['total_pasivo']]);
        $add(['Patrimonio', $resumen['total_patrimonio']]);
        $add(['Utilidad Neta', $resumen['utilidad_neta']]);
        $add(['Cartera Neta', $resumen['cartera_neta']]);
        $add(['Diferencia contable', $resumen['diferencia_contable']]);
        $add([]);

        $add(['Observaciones', $this->analisis->observaciones ?? '—']);
        $add(['Diligenciado por', $this->analisis->diligenciadoPor?->name ?? '—']);
        $add(['Fecha de confirmación', $this->analisis->diligenciado_por_at?->format('Y-m-d H:i') ?? '—']);

        $this->boldRows = $bold;

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        foreach ($this->boldRows as $rowNumber) {
            $sheet->getStyle("A{$rowNumber}:H{$rowNumber}")->getFont()->setBold(true);
        }

        if ($this->analisis->estado !== 'confirmado') {
            $sheet->getStyle('A2:H2')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FEF3C7');
        }

        return [];
    }

    private function encabezado(array $anios): array
    {
        $fila = ['Concepto'];
        foreach ($anios as $index => $anio) {
            $fila[] = (string) $anio;
            $fila[] = 'Análisis Estructural';
            if ($index > 0) {
                $fila[] = 'Variación %';
            }
        }
        return $fila;
    }

    /**
     * Fila [label, valor1, estructural1, valor2, variacion2, estructural2, ...]
     * para una sección con valores + análisis estructural + variación
     * relativa por año (Activo/Pasivo/Patrimonio/Utilidad Neta).
     */
    private function filaConcepto(string $label, string $clave, array $seccion, array $anios, ?string $claveValor = null): array
    {
        $claveValor ??= $clave;
        $fila = [$label];
        foreach ($anios as $index => $anio) {
            $fila[] = $seccion['valores'][$claveValor][$anio] ?? ($seccion[$claveValor][$anio] ?? 0);
            if ($index > 0) {
                $fila[] = $this->pct($seccion['variacion'][$claveValor][$anio] ?? null);
            }
            $fila[] = $this->pct($seccion['estructural'][$claveValor][$anio] ?? null);
        }
        return $fila;
    }

    private function valoresPorAnio(array $serie, array $anios): array
    {
        return array_map(fn ($anio) => $serie[$anio] ?? 0, $anios);
    }

    private function pct(?float $value): string
    {
        return $value === null ? 'N/A' : round($value * 100, 2) . '%';
    }
}
