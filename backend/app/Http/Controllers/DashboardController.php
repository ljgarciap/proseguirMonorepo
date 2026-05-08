<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/dashboard/stats",
     *     summary="Obtener estadísticas globales del dashboard",
     *     description="Retorna métricas de Cartera, Factoring y Confirming con filtros opcionales.",
     *     tags={"Dashboard"},
     *     @OA\Parameter(
     *         name="fecha_inicio",
     *         in="query",
     *         description="Fecha de inicio (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="fecha_fin",
     *         in="query",
     *         description="Fecha de fin (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="cliente",
     *         in="query",
     *         description="Nombre o identificación del cliente/emisor",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Operación exitosa"
     *     )
     * )
     */
    public function stats(Request $request)
    {
        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');
        $cliente = $request->query('cliente');
        $categoria = $request->query('categoria'); // New parameter

        $response = [];

        if (!$categoria || $categoria === 'cartera') {
            $response['cartera'] = $this->getCarteraStats($fechaInicio, $fechaFin, $cliente);
        }

        if (!$categoria || $categoria === 'factoring' || $categoria === 'pagos') {
            $response['factoring'] = $this->getFactoringStats($fechaInicio, $fechaFin, $cliente);
        }

        if (!$categoria || $categoria === 'confirming') {
            $response['confirming'] = $this->getConfirmingStats($fechaInicio, $fechaFin, $cliente);
        }

        if (!$categoria || $categoria === 'compraventa') {
            $response['compraventa'] = $this->getCompraventaStats($fechaInicio, $fechaFin, $cliente);
        }

        return response()->json($response);
    }

    private function getApplyFilters($fechaInicio, $fechaFin, $cliente)
    {
        return function($query, $dateField = null) use ($fechaInicio, $fechaFin, $cliente) {
            if ($cliente) {
                $table = $query->getModel()->getTable();
                $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
                
                $idCol = 'identificacion';
                if (in_array('nit_cliente', $columns)) $idCol = 'nit_cliente';
                elseif (in_array('nit', $columns)) $idCol = 'nit';
                elseif (in_array('emisor_nit', $columns)) $idCol = 'emisor_nit';

                $nameCol = 'cliente';
                if (!in_array('cliente', $columns) && in_array('emisor', $columns)) $nameCol = 'emisor';

                $query->where(function($q) use ($cliente, $idCol, $nameCol) {
                    $q->where($nameCol, 'LIKE', "%{$cliente}%")
                      ->orWhere($idCol, 'LIKE', "%{$cliente}%");
                });
            }
            if ($fechaInicio && $dateField) {
                if ($dateField === 'created_at') $query->whereDate($dateField, '>=', $fechaInicio);
                else $query->where($dateField, '>=', $fechaInicio);
            }
            if ($fechaFin && $dateField) {
                if ($dateField === 'created_at') $query->whereDate($dateField, '<=', $fechaFin);
                else $query->where($dateField, '<=', $fechaFin);
            }
            return $query;
        };
    }

    private function getCarteraStats($fechaInicio, $fechaFin, $cliente)
    {
        $applyFilters = $this->getApplyFilters($fechaInicio, $fechaFin, $cliente);
        $carteraQuery = $applyFilters(\App\Models\OperacionCartera::query(), 'created_at');
        $totalCarteraCount = (clone $carteraQuery)->count();
        $totalSaldoCapital = (clone $carteraQuery)->sum('saldo_capital');
        $uniqueClients = (clone $carteraQuery)->distinct('identificacion')->count();
        $movsPerClient = $uniqueClients > 0 ? round($totalCarteraCount / $uniqueClients, 1) : 0;
        
        // Vencido vs Mora (Separados)
        $vencidoQuery = (clone $carteraQuery)->where('dias_vencido', '>', 0);
        $totalVencidoVal = (clone $vencidoQuery)->sum('valor_vencido');
        $totalMoraVal = (clone $carteraQuery)->sum('valor_mora');
        
        $moraIndex = $totalSaldoCapital > 0 ? ($totalMoraVal / $totalSaldoCapital) * 100 : 0;
        
        // Ranked Clients (Saldos Actuales Operación)
        $clientRanking = (clone $carteraQuery)
            ->select('cliente', 'identificacion', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_ops'), \Illuminate\Support\Facades\DB::raw('SUM(saldo_capital) as saldo_total'))
            ->groupBy('cliente', 'identificacion')
            ->orderBy('saldo_total', 'desc')
            ->limit(10)
            ->get();

        // Debtors in Mora (Aggregated per Client)
        $currentDebtors = (clone $carteraQuery)->where('valor_mora', '>', 0)
            ->select('cliente', 'identificacion')
            ->selectRaw('SUM(valor_mora) as valor_mora')
            ->selectRaw('MAX(dias_vencido) as dias_vencido')
            ->groupBy('cliente', 'identificacion')
            ->orderBy('valor_mora', 'desc')
            ->limit(10)
            ->get();

        // Daily Disbursements Breakdown (Reporte de Desembolsos)
        $dailyDisbursements = (clone $carteraQuery)
            ->selectRaw("
                CASE 
                    WHEN fecha_desembolso LIKE '%/%' THEN DATE(STR_TO_DATE(fecha_desembolso, '%d/%m/%Y'))
                    ELSE DATE(COALESCE(fecha_desembolso, created_at))
                END as fecha, 
                cliente, 
                identificacion,
                SUM(valor_desembolso) as total
            ")
            ->groupBy(\Illuminate\Support\Facades\DB::raw("
                CASE 
                    WHEN fecha_desembolso LIKE '%/%' THEN DATE(STR_TO_DATE(fecha_desembolso, '%d/%m/%Y'))
                    ELSE DATE(COALESCE(fecha_desembolso, created_at))
                END
            "), 'cliente', 'identificacion')
            ->orderBy('fecha', 'desc')
            ->get();

        $sectoresCiudades = (clone $carteraQuery)
            ->select('ciudad', \Illuminate\Support\Facades\DB::raw('SUM(saldo_capital) as total'))
            ->groupBy('ciudad')
            ->orderBy('total', 'desc')
            ->get();

        // Activity Distribution (Actividad Económica por Saldo Capital)
        $actividadEconomica = (clone $carteraQuery)
            ->select('sector_economico', \Illuminate\Support\Facades\DB::raw('SUM(saldo_capital) as total'))
            ->groupBy('sector_economico')
            ->orderBy('total', 'desc')
            ->get();

        // Amortization Plan Distribution
        $amortizacion = (clone $carteraQuery)
            ->select('plan_amortizacion', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('plan_amortizacion')
            ->get();

        return [
            'count' => $totalCarteraCount,
            'unique_clients' => $uniqueClients,
            'movs_per_client' => $movsPerClient,
            'saldo_capital' => (float)$totalSaldoCapital,
            'mora_index' => round($moraIndex, 4), 
            'total_mora' => (float)$totalMoraVal,
            'total_vencido' => (float)$totalVencidoVal,
            'ciudades' => $sectoresCiudades,
            'actividad' => $actividadEconomica,
            'amortizacion' => $amortizacion,
            'client_ranking' => $clientRanking,
            'debtors' => $currentDebtors,
            'daily_disbursements' => $dailyDisbursements
        ];
    }

    private function getFactoringStats($fechaInicio, $fechaFin, $cliente)
    {
        $applyFilters = $this->getApplyFilters($fechaInicio, $fechaFin, $cliente);
        $factoringOpQuery = $applyFilters(\App\Models\OperacionFactoring::query(), 'created_at');
        $factoringOpCount = (clone $factoringOpQuery)->count();
        $totalFinanced = (clone $factoringOpQuery)->sum('monto');
        $totalDisbursed = (clone $factoringOpQuery)->sum('valor_desembolsado');
        $totalReserva = (clone $factoringOpQuery)->sum('valor_reserva');
        $avgTasaFactoring = (clone $factoringOpQuery)->avg('tasa_descuento');
        
        // Exposición por Pagador
        $exposureByPayer = (clone $factoringOpQuery)
            ->select('pagador', \Illuminate\Support\Facades\DB::raw('SUM(monto) as total'))
            ->groupBy('pagador')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        // Vencimientos Factoring
        $vencimientos = (clone $factoringOpQuery)
            ->select('pagador', 'nit_pagador as identificacion', 'fecha_vencimiento', 'monto')
            ->orderBy('fecha_vencimiento', 'asc')
            ->whereNotNull('fecha_vencimiento')
            ->limit(10)
            ->get()
            ->map(function($v) {
                $today = now();
                $fechaStr = $v->fecha_vencimiento;
                $venc = null;

                if ($fechaStr) {
                    if (strpos($fechaStr, '/') !== false) {
                        try {
                            $venc = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $fechaStr);
                        } catch (\Exception $e) {
                            try {
                                $venc = \Illuminate\Support\Carbon::parse($fechaStr);
                            } catch (\Exception $e2) {
                                $venc = now();
                            }
                        }
                    } else {
                        try {
                            $venc = \Illuminate\Support\Carbon::parse($fechaStr);
                        } catch (\Exception $e) {
                            $venc = now();
                        }
                    }
                } else {
                    $venc = now();
                }

                $diff = $today->diffInDays($venc, false);
                
                return [
                    'pagador' => $v->pagador,
                    'fecha' => $fechaStr,
                    'monto' => $v->monto,
                    'dias' => (int)$diff,
                    'estado' => $diff < 0 ? 'Vencido' : ($diff <= 15 ? 'Por Vencer' : 'Vigente')
                ];
            });

        $pagosQuery = $applyFilters(\App\Models\PagoFactoring::query(), 'created_at');
        $pagosCount = (clone $pagosQuery)->count();
        $totalCollected = (clone $pagosQuery)->sum('monto_pagado');
        $efficiencyScore = (clone $pagosQuery)->avg('dias_cartera');
        $earlyPaymentCost = (clone $pagosQuery)->sum('descuento_financiero');
        $outstandingBalance = (clone $pagosQuery)->sum('saldo_restante');
        
        // Monto Pagado a lo largo del tiempo (Timeline)
        $paymentTimeline = (clone $pagosQuery)
            ->selectRaw("
                CASE 
                    WHEN fecha_pago LIKE '%/%' THEN DATE(STR_TO_DATE(fecha_pago, '%d/%m/%Y'))
                    ELSE DATE(COALESCE(fecha_pago, created_at))
                END as fecha, 
                SUM(monto_pagado) as total
            ")
            ->groupBy(\Illuminate\Support\Facades\DB::raw("
                CASE 
                    WHEN fecha_pago LIKE '%/%' THEN DATE(STR_TO_DATE(fecha_pago, '%d/%m/%Y'))
                    ELSE DATE(COALESCE(fecha_pago, created_at))
                END
            "))
            ->orderBy('fecha', 'asc')
            ->limit(30)
            ->get();

        // Distribución de Pagos por Cliente
        $paymentDistribution = (clone $pagosQuery)
            ->select('cliente', \Illuminate\Support\Facades\DB::raw('SUM(monto_pagado) as total'))
            ->groupBy('cliente')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        // Registro de Pagos (Table)
        $paymentEntries = (clone $pagosQuery)
            ->select('cliente', 'nit as identificacion', 'factura_nro', 'dias_cartera', 'monto_pagado')
            ->orderBy('created_at', 'desc')
            ->get();

        // Daily Payments Breakdown
        $dailyPayments = (clone $pagosQuery)
            ->selectRaw("
                CASE 
                    WHEN fecha_pago LIKE '%/%' THEN DATE(STR_TO_DATE(fecha_pago, '%d/%m/%Y'))
                    ELSE DATE(COALESCE(fecha_pago, created_at))
                END as fecha, 
                cliente, 
                nit as identificacion,
                SUM(valor_nominal) as capital, 
                SUM(descuento_financiero) as intereses, 
                SUM(monto_pagado) as total
            ")
            ->groupBy(\Illuminate\Support\Facades\DB::raw("
                CASE 
                    WHEN fecha_pago LIKE '%/%' THEN DATE(STR_TO_DATE(fecha_pago, '%d/%m/%Y'))
                    ELSE DATE(COALESCE(fecha_pago, created_at))
                END
            "), 'cliente', 'nit')
            ->orderBy('fecha', 'desc')
            ->limit(30)
            ->get();

        // Tasa de Colocación Ponderada
        $totalValorParaPonderar = (clone $factoringOpQuery)->where('valor_desembolsado', '>', 0)->sum('valor_desembolsado');
        $sumaTasaPorValor = (clone $factoringOpQuery)->where('valor_desembolsado', '>', 0)
            ->selectRaw('SUM(tasa_descuento * valor_desembolsado) as total')
            ->value('total');
        
        $tasaPonderada = $totalValorParaPonderar > 0 ? ($sumaTasaPorValor / $totalValorParaPonderar) : $avgTasaFactoring;

        // Distribución de Tasas para Gráfico de Tortas
        $tasaDistribution = (clone $factoringOpQuery)
            ->select('tasa_descuento as tasa', \Illuminate\Support\Facades\DB::raw('SUM(valor_desembolsado) as total'))
            ->groupBy('tasa_descuento')
            ->orderBy('tasa', 'asc')
            ->get();

        return [
            'op_count' => $factoringOpCount,
            'volumen_total' => (float)$totalFinanced,
            'valor_desembolsado' => (float)$totalDisbursed,
            'valor_reserva' => (float)$totalReserva,
            'avg_tasa' => $avgTasaFactoring ? round($avgTasaFactoring, 2) : 0,
            'tasa_ponderada' => round($tasaPonderada, 2),
            'tasa_distribution' => $tasaDistribution,
            'pagos_count' => $pagosCount,
            'total_collected' => (float)$totalCollected,
            'efficiency_score' => $efficiencyScore ? round($efficiencyScore, 1) : 0,
            'early_payment_cost' => (float)$earlyPaymentCost,
            'outstanding_balance' => (float)$outstandingBalance,
            'daily_payments' => $dailyPayments,
            'payment_timeline' => $paymentTimeline,
            'payment_distribution' => $paymentDistribution,
            'payment_entries' => $paymentEntries,
            'exposure_by_payer' => $exposureByPayer,
            'vencimientos' => $vencimientos
        ];
    }

    private function getConfirmingStats($fechaInicio, $fechaFin, $cliente)
    {
        $applyFilters = $this->getApplyFilters($fechaInicio, $fechaFin, $cliente);
        $confirmingQuery = $applyFilters(\App\Models\OperacionConfirming::query(), 'created_at');
        $confirmingCount = (clone $confirmingQuery)->count();
        $totalConfirmingVal = (clone $confirmingQuery)->sum('valor_nominal');
        $totalRendimientos = (clone $confirmingQuery)->sum('rendimientos_proyectados');
        $totalPagarDeudores = (clone $confirmingQuery)->sum('valor_pagar_deudor');
        $avgTasaConfirming = (clone $confirmingQuery)->avg('tasa_factor');

        // Análisis de Emisores
        $analysisEmitters = (clone $confirmingQuery)
            ->select('emisor', 'emisor_nit as identificacion', \Illuminate\Support\Facades\DB::raw('SUM(valor_nominal) as total'))
            ->groupBy('emisor', 'emisor_nit')
            ->orderBy('total', 'desc')
            ->get();

        // Tabla de Vencimientos y Días
        $vencimientosConfirming = (clone $confirmingQuery)
            ->select('id_titulo', 'emisor', 'emisor_nit as identificacion', 'fecha_final', 'dias')
            ->orderBy('fecha_final', 'asc')
            ->limit(15)
            ->get()
            ->map(function($v) {
                $fecha = $v->fecha_final;
                if (strpos($fecha, '/') !== false) {
                    try {
                        $fecha = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $fecha)->format('Y-m-d');
                    } catch (\Exception $e) {}
                }
                return [
                    'id_titulo' => $v->id_titulo,
                    'emisor' => $v->emisor,
                    'fecha_final' => $fecha,
                    'dias' => $v->dias
                ];
            });

        // Gráfico de Barras de Tasa Media
        $avgTasaByEmisor = (clone $confirmingQuery)
            ->select('emisor', \Illuminate\Support\Facades\DB::raw('AVG(tasa_factor) as avg_tasa'))
            ->groupBy('emisor')
            ->orderBy('avg_tasa', 'desc')
            ->get();

        // Rendimientos por Emisor
        $rendimientosByEmisor = (clone $confirmingQuery)
            ->select('emisor', 'emisor_nit as identificacion')
            ->selectRaw('SUM(valor_nominal) as total_nominal')
            ->selectRaw('SUM(rendimientos_proyectados) as total_rendimientos')
            ->groupBy('emisor', 'emisor_nit')
            ->get()
            ->map(function($r) {
                return [
                    'emisor' => $r->emisor,
                    'valor_nominal' => (float)$r->total_nominal,
                    'rendimientos' => (float)$r->total_rendimientos
                ];
            });

        return [
            'count' => $confirmingCount,
            'total_val' => (float)$totalConfirmingVal,
            'rendimientos_proyectados' => (float)$totalRendimientos,
            'total_pagar_deudores' => (float)$totalPagarDeudores,
            'avg_tasa' => $avgTasaConfirming ? round($avgTasaConfirming, 2) : 0,
            'analisis_emisores' => $analysisEmitters,
            'vencimientos' => $vencimientosConfirming,
            'tasa_media_emisor' => $avgTasaByEmisor,
            'rendimientos_emisor' => $rendimientosByEmisor
        ];
    }

    private function getCompraventaStats($fechaInicio, $fechaFin, $cliente)
    {
        $applyFilters = $this->getApplyFilters($fechaInicio, $fechaFin, $cliente);
        $query = $applyFilters(\App\Models\Compraventa::query(), 'created_at');
        
        $totalVal = (clone $query)->sum('valor');
        $count = (clone $query)->count();
        $uniqueVendedores = (clone $query)->distinct('nit_vendedor')->count();
        
        $topVendedores = (clone $query)
            ->select('vendedor', 'nit_vendedor', \Illuminate\Support\Facades\DB::raw('SUM(valor) as total'))
            ->groupBy('vendedor', 'nit_vendedor')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();
            
        $topCompradores = (clone $query)
            ->select('comprador', 'nit_comprador', \Illuminate\Support\Facades\DB::raw('SUM(valor) as total'))
            ->groupBy('comprador', 'nit_comprador')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        $vencimientos = (clone $query)
            ->select('nro_factura', 'vendedor', 'comprador', 'fecha_vencimiento', 'valor')
            ->orderBy('fecha_vencimiento', 'asc')
            ->limit(15)
            ->get();

        return [
            'total_val' => (float)$totalVal,
            'count' => $count,
            'unique_vendedores' => $uniqueVendedores,
            'top_vendedores' => $topVendedores,
            'top_compradores' => $topCompradores,
            'vencimientos' => $vencimientos
        ];
    }
}
