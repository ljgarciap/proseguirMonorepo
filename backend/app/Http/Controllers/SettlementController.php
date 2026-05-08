<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PagoFactoring;
use App\Models\OperacionFactoring;
use Illuminate\Support\Facades\DB;

class SettlementController extends Controller
{
    public function reconcile(Request $request)
    {
        $pendingPayments = PagoFactoring::where('estado_liquidacion', 'pendiente')->get();
        $processedCount = 0;
        $errors = [];

        foreach ($pendingPayments as $pago) {
            try {
                // Helper to parse dates
                $parseDate = function($dateStr) {
                    if (!$dateStr) return null;
                    if (str_contains($dateStr, '/')) {
                        return \Carbon\Carbon::createFromFormat('d/m/Y', $dateStr);
                    }
                    return \Carbon\Carbon::parse($dateStr);
                };

                // A. ALWAYS calculate/refresh days
                $fechaPago = $parseDate($pago->fecha_pago);
                $fechaFinal = $parseDate($pago->fecha_final);
                $fechaInicial = $pago->fecha_inicial ? $parseDate($pago->fecha_inicial) : null;

                if ($fechaPago && $fechaFinal) {
                    $pago->dias_sobrantes = (int)$fechaPago->diffInDays($fechaFinal, false);
                    if ($fechaInicial) {
                        $pago->dias_pagos = (int)$fechaInicial->diffInDays($fechaPago);
                    }
                }

                // B. Try to find the matching OP for settlement
                $facturaPago = trim($pago->factura_nro);
                $nitPago = preg_replace('/[^0-9Kk]/', '', $pago->cc_o_nit);

                $op = OperacionFactoring::where(function($query) use ($facturaPago) {
                        $query->where('factura_numero', $facturaPago)
                              ->orWhere(DB::raw('TRIM(LEADING "0" FROM factura_numero)'), trim($facturaPago, '0'));
                    })
                    ->where(function($query) use ($nitPago) {
                        $query->where(DB::raw('REGEXP_REPLACE(nit_pagador, "[^0-9Kk]", "")'), $nitPago);
                    })
                    ->first();

                $updateData = [
                    'dias_pagos' => $pago->dias_pagos,
                    'dias_sobrantes' => $pago->dias_sobrantes,
                ];

                if ($op) {
                    // Initialize saldo_pendiente if it is null
                    if ($op->saldo_pendiente === null) {
                        $op->saldo_pendiente = (float)$op->monto;
                    }

                    // Update Balance
                    $op->saldo_pendiente -= (float)$pago->monto_pagado;
                    $op->save();

                    // Calculate Settlement Metrics
                    $interesesDiarios = $op->intereses_diarios;
                    if (!$interesesDiarios) {
                        $interesesDiarios = (( (float)$op->valor_aprobado * (float)$op->tasa_descuento ) / 30) / 100;
                    }

                    $interesesPagados = (float)$pago->dias_pagos * $interesesDiarios;
                    $devolucion = $interesesPagados - (float)$pago->descuento_financiero;
                    $margen = (float)$op->valor_reserva - $devolucion;

                    $updateData['intereses_diarios'] = $interesesDiarios;
                    $updateData['intereses_pagados'] = $interesesPagados;
                    $updateData['devolucion_descuento'] = $devolucion;
                    $updateData['margen_reserva'] = $margen;
                    $updateData['saldo_restante'] = $op->saldo_pendiente; // Update payment with current invoice balance
                    $updateData['estado_liquidacion'] = 'completado';
                    $processedCount++;
                }

                // Save whatever we have (at least the days)
                $pago->update($updateData);

            } catch (\Exception $e) {
                $errors[] = "Error processing payment ID {$pago->id}: " . $e->getMessage();
            }
        }

        return response()->json([
            'message' => 'Proceso de conciliación completado',
            'processed_count' => $processedCount,
            'errors' => $errors
        ]);
    }
}
