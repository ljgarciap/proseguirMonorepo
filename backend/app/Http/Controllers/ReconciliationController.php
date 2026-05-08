<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ContableFactura;
use App\Models\ContableBanco;
use App\Models\ContableAuxiliar;
use App\Models\ContableGasto;
use Illuminate\Support\Facades\DB;

class ReconciliationController extends Controller
{
    public function reconcile(Request $request)
    {
        return DB::transaction(function () {
            $matchedCount = 0;
            $gastoCount = 0;

            // 1. Get all pending bank records
            $pendingBancos = ContableBanco::where('status', 'pendiente')->get();

            foreach ($pendingBancos as $banco) {
                // 2. Search for a pending factura with EXACT amount (valor)
                // We use FIFO: earliest pending factura with that amount
                $factura = ContableFactura::where('status', 'pendiente')
                    ->where('total', $banco->valor)
                    ->orderBy('fecha', 'asc')
                    ->first();

                if ($factura) {
                    // MATCH!
                    // Update statuses
                    $banco->update([
                        'status' => 'conciliado',
                        'reconciled_id' => $factura->id
                    ]);
                    $factura->update([
                        'status' => 'conciliado',
                        'reconciled_id' => $banco->id
                    ]);

                    // Generate Auxiliar record
                    ContableAuxiliar::create([
                        'import_batch_id' => $banco->import_batch_id,
                        'source_factura_id' => $factura->id,
                        'source_banco_id' => $banco->id,
                        'unique_hash' => md5('aux_' . $banco->id . '_' . $factura->id),
                        'fecha' => $banco->fecha,
                        'comprobante' => 'CONCILIACION',
                        'tercero' => $factura->nombre ?? $factura->cliente,
                        'documento' => $factura->factura,
                        'detalle' => "Pago Factura {$factura->factura} - Banco Ref",
                        'nit' => $factura->nit,
                        'base_local' => $factura->vlr_bruto,
                        'debito_local' => $banco->valor,
                        'credito_local' => 0,
                        'saldo_local' => 0,
                    ]);

                    $matchedCount++;
                } else {
                    // NO MATCH -> Classify as Gasto
                    $banco->update(['status' => 'gasto']);

                    ContableGasto::create([
                        'import_batch_id' => $banco->import_batch_id,
                        'source_banco_id' => $banco->id,
                        'unique_hash' => md5('gasto_' . $banco->id),
                        'fecha' => $banco->fecha,
                        'comprobante_contable' => 'EGRESO_AUTO',
                        'no_factura' => 'N/A',
                        'nit' => 'N/A',
                        'tercero' => 'BANCO (Gasto Automático)',
                        'concepto' => $banco->descripcion,
                        'valor' => $banco->valor,
                        'cta_contable' => '519595', // General expenses placeholder
                        'observaciones' => "Gasto detectado en conciliación: {$banco->descripcion}",
                    ]);

                    $gastoCount++;
                }
            }

            return response()->json([
                'message' => 'Conciliación completada exitosamente',
                'matched' => $matchedCount,
                'gastos' => $gastoCount
            ]);
        });
    }
}
