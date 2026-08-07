<?php

namespace App\Exports;

use App\Models\CreditoOrdinario;
use App\Models\InformeTecnico;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Documento consolidado del Informe Técnico (SCRUM-120 Fase 2): encabezado,
 * sección Ingeniero con fórmulas, sección Coordinador con fórmulas,
 * observaciones de ambos y trazabilidad de quién/cuándo diligenció cada
 * parte. Marca "BORRADOR" si el informe todavía no está `registrado`.
 */
class InformeTecnicoExport implements FromArray, WithStyles, WithTitle, ShouldAutoSize
{
    private array $boldRows = [];

    public function __construct(private CreditoOrdinario $credito, private InformeTecnico $informe)
    {
    }

    public function title(): string
    {
        return 'Informe Tecnico';
    }

    public function array(): array
    {
        $rows = [];
        $bold = [];

        $addBold = function (array $row) use (&$rows, &$bold) {
            $bold[] = count($rows) + 1;
            $rows[] = $row;
        };
        $add = function (array $row) use (&$rows) {
            $rows[] = $row;
        };

        $solicitudCredito = $this->credito->solicitudCredito;
        $cliente = $solicitudCredito?->cliente;

        $addBold(['INFORME TÉCNICO — CRÉDITO CONSTRUCTOR']);
        if ($this->informe->estado !== 'registrado') {
            $addBold(['*** BORRADOR — INFORME NO FINALIZADO ***']);
        }
        $add([]);
        $add(['No. de Crédito', $this->credito->numero_solicitud]);
        $add(['Proyecto', $solicitudCredito?->proyecto ?? '—']);
        $add(['Ciudad', $cliente?->ciudad ?? '—']);
        $add(['Dirección', $cliente?->direccion ?? '—']);
        $add(['Solicitante', $this->credito->cliente?->name ?? '—']);
        $add(['Tipo de Crédito', 'Constructor']);
        $add(['Estado del Expediente', $this->credito->estado]);
        $add([]);

        $addBold(['SECCIÓN INGENIERO']);
        $v = $this->informe->ventas_totales_proyecto ?? [];
        $add(['Ventas Totales Proyecto', 'Valor', '% Sobre Ventas']);
        $add(['Casas', $v['casas'] ?? 0, $this->pct($v['porcentajes']['casas'] ?? 0)]);
        $add(['Apartamentos', $v['apartamentos'] ?? 0, $this->pct($v['porcentajes']['apartamentos'] ?? 0)]);
        $add(['Parqueaderos', $v['parqueaderos'] ?? 0, $this->pct($v['porcentajes']['parqueaderos'] ?? 0)]);
        $add(['Conexión gas/arras desist.', $v['conexion_gas_arras'] ?? 0, $this->pct($v['porcentajes']['conexion_gas_arras'] ?? 0)]);
        $add(['Local comercial', $v['local_comercial'] ?? 0, $this->pct($v['porcentajes']['local_comercial'] ?? 0)]);
        $add(['Cuartos útiles', $v['cuartos_utiles'] ?? 0, $this->pct($v['porcentajes']['cuartos_utiles'] ?? 0)]);
        $add(['Otros', $v['otros'] ?? 0, $this->pct($v['porcentajes']['otros'] ?? 0)]);
        $addBold(['Total Ventas', $v['total_ventas'] ?? 0]);
        $add([]);

        $c = $this->informe->costos ?? [];
        $add(['Costos (incluido Costo Financiero)']);
        $add(['Lote', $c['lote'] ?? 0]);
        $add(['Directos', $c['directos'] ?? 0]);
        $add(['Directos Urbanismo', $c['directos_urbanismo'] ?? 0]);
        $add(['Indirectos', $c['indirectos'] ?? 0]);
        $add(['Honorarios', $c['honorarios'] ?? 0]);
        $add(['Incremento en costos', $c['incremento_costos'] ?? 0]);
        $add(['Financieros', $c['financieros'] ?? 0]);
        $addBold(['Total Costos', $c['total_costos'] ?? 0, $this->pct($c['porcentaje_costos'] ?? 0)]);
        $add([]);

        $i = $this->informe->invertido ?? [];
        $add(['Invertido']);
        $add(['Lote', $i['lote'] ?? 0]);
        $add(['Costos Directos', $i['costos_directos'] ?? 0]);
        $add(['Costos Indirectos', $i['costos_indirectos'] ?? 0]);
        $add(['Cuotas Iniciales en Fiducia', $i['cuotas_iniciales_fiducia'] ?? 0]);
        $addBold(['Total Invertido', $i['total_invertido'] ?? 0, $this->pct($i['porcentaje_invertido'] ?? 0)]);
        $add(['Recursos propios', $i['recursos_propios'] ?? 0]);
        $add(['Cuotas Iniciales Ya Pagadas', $i['cuotas_iniciales_ya_pagadas'] ?? 0]);
        $addBold(['Total Fuentes', $i['total_fuentes'] ?? 0]);
        $add([]);

        $add(['Observaciones del Ingeniero', $this->informe->observaciones_ingeniero ?? '—']);
        $add(['Diligenciado por (Ingeniero)', $this->informe->ingeniero?->name ?? '—']);
        $add(['Fecha (Ingeniero)', $this->informe->diligenciado_por_ingeniero_at?->format('Y-m-d H:i') ?? '—']);
        $add([]);

        $addBold(['SECCIÓN COORDINADOR COMERCIAL']);
        $cs = $this->informe->credito_solicitado ?? [];
        $add(['Crédito Solicitado', 'Valor', '% Sobre Ventas']);
        $add(['Crédito Solicitado', $cs['credito_solicitado'] ?? 0, $this->pct($cs['porcentaje_sobre_ventas'] ?? 0)]);
        $add(['Aptos. Vendidos', $cs['aptos_vendidos'] ?? 0, $this->pct($cs['porcentaje_aptos_vendidos_sobre_ventas'] ?? 0)]);
        $add(['Cuotas Iniciales Ya Pagadas (= Invertido Ingeniero)', $cs['cuotas_iniciales_ya_pagadas'] ?? 0]);
        $add(['% para cuotas iniciales pendientes', $this->pct($cs['porcentaje_cuotas_iniciales_pendientes'] ?? 0)]);
        $add(['Cuotas Iniciales Pendientes', $cs['cuotas_iniciales_pendientes'] ?? 0]);
        $addBold(['Saldo por Recaudar Contraentrega (vendidos)', $cs['saldo_recaudar_contraentrega_vendidos'] ?? 0]);
        $add([]);

        $s = $this->informe->saldos_por_recaudar_contraentrega ?? [];
        $add(['Saldo por Recaudar Contraentrega (por vender)', 'Valor', '% Sobre Ventas']);
        $add(['Aptos x Vender', $s['aptos_x_vender'] ?? 0, $this->pct($s['porcentaje_sobre_ventas'] ?? 0)]);
        $add(['% para cuotas iniciales (por vender)', $this->pct($s['porcentaje_cuotas_iniciales'] ?? 0)]);
        $add(['Cuotas Iniciales', $s['cuotas_iniciales'] ?? 0]);
        $add(['Cuotas Iniciales Pendientes', $s['cuotas_iniciales_pendientes'] ?? 0]);
        $addBold(['Saldo por Recaudar Contraentrega (por vender)', $s['saldo_recaudar_contraentrega_por_vender'] ?? 0]);
        $addBold(['Total Pendiente por Recaudar', $s['total_pendiente_por_recaudar'] ?? 0]);
        $add([]);

        $af = $this->informe->analisis_financiacion ?? [];
        $add(['Análisis de Financiación']);
        $add(['Costo de la Obra', $af['costo_obra'] ?? 0]);
        $add(['(-) Valor Invertido', $af['valor_invertido'] ?? 0]);
        $add(['(-) Crédito', $af['credito'] ?? 0]);
        $add(['Saldo X Financiar (bruto)', $af['saldo_x_financiar_bruto'] ?? 0]);
        $add(['(-) Cuotas Iniciales x Recaudar', $af['cuotas_iniciales_x_recaudar'] ?? 0]);
        $addBold(['Saldo X Financiar', $af['saldo_x_financiar'] ?? 0]);
        $add(['(-) Cuotas iniciales Pendientes (por vender)', $af['cuotas_iniciales_pendientes_por_vender'] ?? 0]);
        $addBold(['Saldo No Financiado', $af['saldo_no_financiado'] ?? 0]);
        $add([]);

        $cob = $this->informe->coberturas ?? [];
        $add(['Coberturas', 'Cobertura', 'Umbral Máximo', 'Semáforo']);
        $add(['A) Peor Escenario (no vende más)', $this->pct($cob['peor_escenario']['cobertura'] ?? 0), $this->pct($cob['peor_escenario']['umbral_maximo'] ?? 0), $cob['peor_escenario']['semaforo'] ?? '—']);
        $add(['B) Mejor Escenario (vende todo)', $this->pct($cob['mejor_escenario']['cobertura'] ?? 0), $this->pct($cob['mejor_escenario']['umbral_maximo'] ?? 0), $cob['mejor_escenario']['semaforo'] ?? '—']);
        $add(['C) Cobertura de Garantía (usa Total Ventas)', $this->pct($cob['cobertura_garantia']['cobertura'] ?? 0), $this->pct($cob['cobertura_garantia']['umbral_maximo'] ?? 0), $cob['cobertura_garantia']['semaforo'] ?? '—']);
        $add([]);

        $add(['Observaciones del Coordinador Comercial', $this->informe->observaciones_coordinador ?? '—']);
        $add(['Diligenciado por (Coordinador)', $this->informe->coordinador?->name ?? '—']);
        $add(['Fecha (Coordinador)', $this->informe->diligenciado_por_coordinador_at?->format('Y-m-d H:i') ?? '—']);

        $this->boldRows = $bold;

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        foreach ($this->boldRows as $rowNumber) {
            $sheet->getStyle("A{$rowNumber}:D{$rowNumber}")->getFont()->setBold(true);
        }

        if ($this->informe->estado !== 'registrado') {
            $sheet->getStyle('A2:D2')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FEF3C7');
        }

        return [];
    }

    private function pct(float $value): string
    {
        return round($value * 100, 2) . '%';
    }
}
