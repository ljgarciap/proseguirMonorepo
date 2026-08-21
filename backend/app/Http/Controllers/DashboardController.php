<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CarteraFactoringExport;

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

        if (!$categoria || $categoria === 'pagos_compraventa') {
            $response['pagos_compraventa'] = $this->getPagosCompraventaStats($fechaInicio, $fechaFin, $cliente);
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
        $totalFinanced = (clone $factoringOpQuery)->sum('valor_aprobado');
        $totalDisbursed = (clone $factoringOpQuery)->sum('valor_aprobado') - (clone $factoringOpQuery)->sum('descuento_financiero');
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
            ->whereNotNull('fecha_vencimiento')
            ->get()
            ->map(function($v) {
                $today = now()->startOfDay();
                $fechaStr = $v->fecha_vencimiento;
                $venc = null;

                if ($fechaStr) {
                    if (strpos($fechaStr, '/') !== false) {
                        try {
                            $venc = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $fechaStr)->startOfDay();
                        } catch (\Exception $e) {
                            try {
                                $venc = \Illuminate\Support\Carbon::parse($fechaStr)->startOfDay();
                            } catch (\Exception $e2) {
                                $venc = now()->startOfDay();
                            }
                        }
                    } else {
                        try {
                            $venc = \Illuminate\Support\Carbon::parse($fechaStr)->startOfDay();
                        } catch (\Exception $e) {
                            $venc = now()->startOfDay();
                        }
                    }
                } else {
                    $venc = now()->startOfDay();
                }

                $diff = $today->diffInDays($venc, false);
                
                return [
                    'pagador' => $v->pagador,
                    'fecha' => $fechaStr,
                    'monto' => $v->monto,
                    'dias' => (int)$diff,
                    'estado' => $diff < 0 ? 'Vencido' : ($diff <= 5 ? 'Por Vencer' : 'Vigente')
                ];
            })
            ->filter(function($v) {
                return $v['dias'] >= 0 && $v['dias'] <= 5;
            })
            ->sortBy('dias')
            ->take(10)
            ->values();

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

        // Distribución de Tasas Ponderadas por Pagador (Para el nuevo gráfico)
        $weightedByPayer = (clone $factoringOpQuery)
            ->where('valor_desembolsado', '>', 0)
            ->select('pagador')
            ->selectRaw('SUM(tasa_descuento * valor_desembolsado) / SUM(valor_desembolsado) as weighted_avg')
            ->groupBy('pagador')
            ->orderBy('weighted_avg', 'desc')
            ->limit(10)
            ->get();

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
            'weighted_by_payer' => $weightedByPayer,
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

    private function getPagosCompraventaStats($fechaInicio, $fechaFin, $cliente)
    {
        $applyFilters = $this->getApplyFilters($fechaInicio, $fechaFin, $cliente);
        $query = $applyFilters(\App\Models\PagoCompraventa::query(), 'created_at');
        
        $totalRecaudado = (clone $query)->sum('valor_recaudado');
        $totalCapital = (clone $query)->sum('capital_pagado');
        $totalDescuento = (clone $query)->sum('valor_descuento');
        $count = (clone $query)->count();
        
        $topPagadores = (clone $query)
            ->select('pagador', 'nit_pagador', \Illuminate\Support\Facades\DB::raw('SUM(valor_recaudado) as total'))
            ->groupBy('pagador', 'nit_pagador')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();
            
        $topClientes = (clone $query)
            ->select('cliente', 'nit_cliente', \Illuminate\Support\Facades\DB::raw('SUM(valor_recaudado) as total'))
            ->groupBy('cliente', 'nit_cliente')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        $ultimosPagos = (clone $query)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return [
            'total_recaudado' => (float)$totalRecaudado,
            'total_capital' => (float)$totalCapital,
            'total_descuento' => (float)$totalDescuento,
            'count' => $count,
            'top_pagadores' => $topPagadores,
            'top_clientes' => $topClientes,
            'ultimos_pagos' => $ultimosPagos
        ];
    }

    /**
     * SCRUM-230 — pestaña "Cartera Factoring" del Dashboard.
     *
     * Cruza cada factura/título de origen con su documento de pagos
     * correspondiente por llave exacta (Doc# para Factoring, Id Título
     * para CompraVenta/Confirming), conservando los registros sin pago.
     * "CompraVenta" en el ticket corresponde a la categoría de subida
     * `opf` (modelo OperacionConfirming) — no al modelo Compraventa, que
     * es un tipo de documento distinto no contemplado en este ticket (ver
     * columnas Id Título/Emisor/Deudor/Valor a Pagar Deudor del ticket,
     * que matchean el esquema OCR de `opf`, no el de `compraventa`).
     *
     * Si hay más de un pago para la misma factura/título, se toma el más
     * reciente (mayor id) — el ticket no contempla pagos parciales
     * múltiples por factura.
     */
    public function carteraFactoring(Request $request)
    {
        $base = $this->carteraFactoringBaseQuery($request);

        $totalFacturas = (clone $base)->count();
        $facturasFactoring = (clone $base)->where('tipo_operacion', 'factoring')->count();
        $facturasCompraventa = (clone $base)->where('tipo_operacion', 'compraventa')->count();
        $valorCarteraTotal = (float) (clone $base)->sum('saldo_despues_pago');
        $totalValorPagado = (float) (clone $base)->where('tiene_pago', 1)->sum('valor_pagado');
        $pagosCoincidentes = (clone $base)->where('tiene_pago', 1)->count();
        $registrosSinPago = (clone $base)->where('tiene_pago', 0)->count();

        $top10 = (clone $base)
            ->select('cliente', \Illuminate\Support\Facades\DB::raw('SUM(saldo_despues_pago) as total'))
            ->groupBy('cliente')
            ->havingRaw('SUM(saldo_despues_pago) > 0')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        $clientesQuery = (clone $base)
            ->select('nit_cliente', 'cliente', \Illuminate\Support\Facades\DB::raw('SUM(saldo_despues_pago) as valor_cartera'))
            ->groupBy('nit_cliente', 'cliente')
            ->havingRaw('SUM(saldo_despues_pago) > 0');

        $buscarCliente = $request->query('buscar_cliente');
        if ($buscarCliente) {
            $buscarCliente = trim($buscarCliente);
            $clientesQuery->having(function ($q) use ($buscarCliente) {
                $q->where('cliente', 'LIKE', "%{$buscarCliente}%")
                  ->orWhere('nit_cliente', 'LIKE', "%{$buscarCliente}%");
            });
        }
        $clientes = $clientesQuery->orderBy('valor_cartera', 'desc')->orderBy('cliente', 'asc')->get();

        $perPage = (int) ($request->query('per_page', 20));
        $detalle = (clone $base)->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'indicadores' => [
                'facturas_origen' => $totalFacturas,
                'facturas_factoring' => $facturasFactoring,
                'facturas_compraventa' => $facturasCompraventa,
                'valor_cartera_total_despues_pago' => $valorCarteraTotal,
                'total_valor_pagado' => $totalValorPagado,
                'pagos_coincidentes' => $pagosCoincidentes,
                'registros_sin_pago' => $registrosSinPago,
            ],
            'top10_clientes' => $top10,
            'clientes' => $clientes,
            'detalle' => $detalle,
        ]);
    }

    /**
     * SCRUM-230 — exporta a Excel el detalle completo (sin paginar) del
     * cruce, respetando los mismos filtros que la consulta del Dashboard.
     * Solo Superadministrador exporta (ver tabla de actores del ticket).
     */
    public function exportCarteraFactoringExcel(Request $request)
    {
        $records = $this->carteraFactoringBaseQuery($request)->orderBy('created_at', 'desc')->get();
        $filename = 'cartera_factoring_' . date('Ymd_His') . '.xlsx';
        return Excel::download(new CarteraFactoringExport($records), $filename);
    }

    /**
     * Query base (sin paginar) del cruce Factoring↔Pagos Factoring y
     * CompraVenta(Confirming)↔Pagos CompraVenta, con filtros de fecha
     * (sobre created_at, mismo criterio que el resto del Dashboard),
     * cliente/NIT y número de factura ya aplicados.
     */
    private function carteraFactoringBaseQuery(Request $request)
    {
        // El ticket especifica cruce por igualdad exacta ("Doc# = Doc#",
        // "Id Título = IdTítulo"), sin normalización — se toma el pago más
        // reciente (mayor id) si hay más de uno para la misma factura/título.
        $latestPagoFactoring = \Illuminate\Support\Facades\DB::table('pago_factorings')
            ->joinSub(
                \Illuminate\Support\Facades\DB::table('pago_factorings')
                    ->selectRaw('factura_nro, MAX(id) as max_id')
                    ->groupBy('factura_nro'),
                'latest_pf',
                function ($join) {
                    $join->on('pago_factorings.factura_nro', '=', 'latest_pf.factura_nro')
                         ->on('pago_factorings.id', '=', 'latest_pf.max_id');
                }
            )
            ->select('pago_factorings.*');

        $latestPagoCompraventa = \Illuminate\Support\Facades\DB::table('pagos_compraventa')
            ->joinSub(
                \Illuminate\Support\Facades\DB::table('pagos_compraventa')
                    ->selectRaw('id_titulo, MAX(id) as max_id')
                    ->groupBy('id_titulo'),
                'latest_pc',
                function ($join) {
                    $join->on('pagos_compraventa.id_titulo', '=', 'latest_pc.id_titulo')
                         ->on('pagos_compraventa.id', '=', 'latest_pc.max_id');
                }
            )
            ->select('pagos_compraventa.*');

        $facturaRows = \Illuminate\Support\Facades\DB::table('operacion_factorings as of')
            ->leftJoinSub($latestPagoFactoring, 'pf', function ($join) {
                $join->on('pf.factura_nro', '=', 'of.factura_numero');
            })
            ->selectRaw("
                'factoring' as tipo_operacion,
                of.id as origen_id,
                of.fecha_desembolso as fecha_inicial,
                of.fecha_vencimiento as fecha_final,
                of.factura_numero as factura_numero,
                of.monto as valor_neto_factura,
                of.nit_cliente as nit_cliente,
                of.cliente as cliente,
                pf.cc_o_nit as nit_pagador,
                pf.pagador as pagador,
                pf.fecha_pago as fecha_pago,
                pf.monto_pagado as valor_pagado,
                (of.monto - COALESCE(pf.monto_pagado, 0)) as saldo_despues_pago,
                of.created_at as created_at,
                CASE WHEN pf.id IS NOT NULL THEN 1 ELSE 0 END as tiene_pago
            ");

        $tituloRows = \Illuminate\Support\Facades\DB::table('operacion_confirmings as oc')
            ->leftJoinSub($latestPagoCompraventa, 'pc', function ($join) {
                $join->on('pc.id_titulo', '=', 'oc.id_titulo');
            })
            ->selectRaw("
                'compraventa' as tipo_operacion,
                oc.id as origen_id,
                oc.fecha_inicial as fecha_inicial,
                oc.fecha_final as fecha_final,
                oc.id_titulo as factura_numero,
                oc.valor_pagar_deudor as valor_neto_factura,
                oc.emisor_nit as nit_cliente,
                oc.emisor as cliente,
                pc.nit_pagador as nit_pagador,
                pc.pagador as pagador,
                pc.fecha_recaudo as fecha_pago,
                pc.total_pagado as valor_pagado,
                (oc.valor_pagar_deudor - COALESCE(pc.total_pagado, 0)) as saldo_despues_pago,
                oc.created_at as created_at,
                CASE WHEN pc.id IS NOT NULL THEN 1 ELSE 0 END as tiene_pago
            ");

        $union = $facturaRows->unionAll($tituloRows);
        $base = \Illuminate\Support\Facades\DB::query()->fromSub($union, 'cartera_factoring');

        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');
        $cliente = $request->query('cliente');
        $factura = $request->query('factura');

        if ($fechaInicio) {
            $base->whereDate('created_at', '>=', $fechaInicio);
        }
        if ($fechaFin) {
            $base->whereDate('created_at', '<=', $fechaFin);
        }
        if ($cliente) {
            $base->where(function ($q) use ($cliente) {
                $q->where('cliente', 'LIKE', "%{$cliente}%")
                  ->orWhere('nit_cliente', 'LIKE', "%{$cliente}%");
            });
        }
        if ($factura) {
            $base->where('factura_numero', 'LIKE', "%{$factura}%");
        }

        return $base;
    }
}
