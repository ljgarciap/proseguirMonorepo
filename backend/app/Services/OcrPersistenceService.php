<?php

namespace App\Services;

use App\Models\OperacionCartera;
use App\Models\OperacionFactoring;
use App\Models\PagoFactoring;
use App\Models\OperacionConfirming;
use App\Models\Compraventa;
use App\Models\PagoCompraventa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OcrPersistenceService
{
    public function processData(array $data, string $categoria, ?string $filename = null, $clientUploadId = null): void
    {
        $data = $this->mapKeysToDatabase($data, $categoria);

        foreach ($data as $key => $row) {
            unset($data[$key]['filename'], $data[$key]['nombre_archivo']);
        }

        if (empty($clientUploadId) && !empty($filename)) {
            $upload = \App\Models\ClientUpload::where('filename', $filename)
                ->orWhere('original_name', $filename)
                ->orWhere('filename', 'like', '%' . basename($filename))
                ->first();
            if ($upload) {
                $clientUploadId = $upload->id;
            }
        }

        $data = $this->applyConsensus($data, $categoria);

        DB::transaction(function () use ($data, $categoria, $clientUploadId) {
            switch ($categoria) {
                case 'cartera':
                    foreach ($data as $row) {
                        if (isset($row['actividad_economica'])) {
                            $row['sector_economico'] = IntelligentMapper::mapActivityToSector($row['actividad_economica']);
                        }
                        if (isset($row['cliente']) && isset($row['identificacion'])) {
                            $row['identificacion'] = ClientMasterService::masterClient($row['cliente'], $row['identificacion'], [
                                'ciudad' => $row['ciudad'] ?? null,
                                'sector_economico' => $row['sector_economico'] ?? null,
                                'actividad_economica' => $row['actividad_economica'] ?? null,
                            ]);
                        }
                        $cleanedRow = collect($row)->except(['operacion', 'saldo_total'])->toArray();
                        $cleanedRow['client_upload_id'] = $clientUploadId;
                        OperacionCartera::create($this->sanitizeRowForDb($cleanedRow));
                    }
                    break;

                case 'op':
                    foreach ($data as $row) {
                        if (empty($row['monto']) || (float) $row['monto'] <= 0) {
                            continue;
                        }
                        if (isset($row['cliente']) && isset($row['nit_cliente'])) {
                            $row['nit_cliente'] = ClientMasterService::masterClient($row['cliente'], $row['nit_cliente']);
                        }
                        $valorAprobado = (float) ($row['valor_aprobado'] ?? 0);
                        $tasa = (float) ($row['tasa_descuento'] ?? 0);
                        $row['intereses_diarios'] = (($valorAprobado * $tasa) / 30) / 100;
                        $row['client_upload_id'] = $clientUploadId;
                        OperacionFactoring::create($this->sanitizeRowForDb($row));
                    }
                    break;

                case 'pagos':
                    foreach ($data as $row) {
                        if (isset($row['cliente']) && isset($row['nit'])) {
                            $row['nit'] = ClientMasterService::masterClient($row['cliente'], $row['nit']);
                        }
                        try {
                            $fPagoStr   = $row['fecha_pago']    ?? null;
                            $fFinalStr  = $row['fecha_final']   ?? null;
                            $fInicialStr = $row['fecha_inicial'] ?? null;
                            if ($fPagoStr && $fFinalStr) {
                                $parseDate = function ($dateStr) {
                                    return str_contains($dateStr, '/')
                                        ? \Carbon\Carbon::createFromFormat('d/m/Y', $dateStr)
                                        : \Carbon\Carbon::parse($dateStr);
                                };
                                $fechaPago    = $parseDate($fPagoStr);
                                $fechaFinal   = $parseDate($fFinalStr);
                                $fechaInicial = $fInicialStr ? $parseDate($fInicialStr) : null;
                                $row['dias_sobrantes'] = $fechaPago->diffInDays($fechaFinal, false);
                                if ($fechaInicial) {
                                    $row['dias_pagos'] = $fechaInicial->diffInDays($fechaPago);
                                }
                            }
                        } catch (\Exception) {}
                        $row['estado_liquidacion'] = 'pendiente';
                        PagoFactoring::create($this->sanitizeRowForDb($row));
                    }
                    break;

                case 'opf':
                    foreach ($data as $row) {
                        if (isset($row['emisor']) && isset($row['emisor_nit'])) {
                            $row['emisor_nit'] = ClientMasterService::masterClient($row['emisor'], $row['emisor_nit']);
                        }
                        OperacionConfirming::create($this->sanitizeRowForDb($row));
                    }
                    break;

                case 'compraventa':
                    foreach ($data as $row) {
                        if (isset($row['vendedor']) && isset($row['nit_vendedor'])) {
                            $row['nit_vendedor'] = ClientMasterService::masterClient($row['vendedor'], $row['nit_vendedor']);
                        }
                        Compraventa::create($this->sanitizeRowForDb($row));
                    }
                    break;

                case 'pagos_compraventa':
                    foreach ($data as $row) {
                        if (isset($row['pagador'])) {
                            $row['nit_pagador'] = ClientMasterService::masterClient($row['pagador'], $row['nit_pagador'] ?? null);
                        }
                        if (isset($row['cliente'])) {
                            $row['nit_cliente'] = ClientMasterService::masterClient($row['cliente'], $row['nit_cliente'] ?? null);
                        }
                        $row['client_upload_id'] = $clientUploadId;
                        PagoCompraventa::create($this->sanitizeRowForDb($row));
                    }
                    break;

                default:
                    throw new \Exception('Categoría no válida');
            }
        });
    }

    private function sanitizeRowForDb(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $isSimple = true;
                foreach ($value as $item) {
                    if (is_array($item) || is_object($item)) { $isSimple = false; break; }
                }
                $row[$key] = $isSimple && count($value) > 0 ? implode(', ', $value) : json_encode($value);
            }
        }
        return $row;
    }

    private function mapKeysToDatabase(array $data, string $categoria): array
    {
        $explicitMap = [
            'Identificación' => 'identificacion', 'ActividadEconómica' => 'actividad_economica',
            'Operación' => 'operacion', 'SaldoTotal' => 'saldo_total', 'PlazoMeses' => 'plazo_meses',
            'TasaInterés' => 'tasa_interes', 'PlanAmortización' => 'plan_amortizacion',
            'GarantíaDetalle' => 'garantia_detalle', 'GarantiaDetalle' => 'garantia_detalle',
            'EstadoGarantia' => 'estado_garantia', 'TipoGarantia' => 'tipo_garantia',
            'FechaDesembolso' => 'fecha_desembolso', 'NumeroRadicado' => 'numero_radicado',
            'EstadoCapital' => 'estado_capital', 'FechaVencimientoCapital' => 'fecha_vencimiento_capital',
            'ValorDesembolso' => 'valor_desembolso', 'SaldoCapital' => 'saldo_capital',
            'Vencido' => 'vencido', 'DiasVencido' => 'dias_vencido', 'ValorVencido' => 'valor_vencido',
            'TieneMora' => 'tiene_mora', 'ValorMora' => 'valor_mora',
            'FechaUltimoAbono' => 'fecha_ultimo_abono', 'ValorUltimoAbono' => 'valor_ultimo_abono',
            'NombreArchivo' => 'filename', 'Cliente' => 'cliente', 'Ciudad' => 'ciudad',
            'Emisor' => 'emisor', 'Deudor' => 'deudor', 'Pagador' => 'pagador',
            'Op_relacionada' => 'op_relacionada', 'Fecha_reliquidacion' => 'fecha_reliquidacion',
            'Valor_Pagar_deudor' => 'valor_pagar_deudor', 'Emisor_nit' => 'emisor_nit',
            'Deudor_nit' => 'deudor_nit',
            // OP
            'NIT_Cliente' => 'nit_cliente', 'Factura_Numero' => 'factura_numero', 'Monto' => 'monto',
            'Dias' => 'dias', 'Tasa_Descuento' => 'tasa_descuento', 'NIT_Pagador' => 'nit_pagador',
            'Fecha_Aprobacion' => 'fecha_aprobacion', 'Valor_Aprobado' => 'valor_aprobado',
            'Valor_Desembolsado' => 'valor_desembolsado', 'Fecha_Vencimiento' => 'fecha_vencimiento',
            'Valor_Reserva' => 'valor_reserva', 'Descuento_Financiero' => 'descuento_financiero',
            // Pagos
            'Pago_Nro' => 'pago_nro', 'Fecha_Pago' => 'fecha_pago', 'NIT' => 'nit',
            'Reliquidacion' => 'reliquidacion', 'Factura_Nro' => 'factura_nro', 'CC_o_NIT' => 'cc_o_nit',
            'Fecha_Inicial' => 'fecha_inicial', 'Fecha_Final' => 'fecha_final',
            'Dias_Cartera' => 'dias_cartera', 'Valor_Titulo' => 'valor_titulo',
            'Valor_Nominal' => 'valor_nominal', 'Monto_Pagado' => 'monto_pagado',
            'Saldo_Restante' => 'saldo_restante', 'Total_Recaudado_Comprobante' => 'total_recaudado_comprobante',
            // OPF
            'Tasa_Factor' => 'tasa_factor', 'ID_Titulo' => 'id_titulo',
            'Reembolso_G_Desembolso' => 'reembolso_g_desembolso', 'Base_Negociacion' => 'base_negociacion',
            'Rendimientos_Proyectados' => 'rendimientos_proyectados',
            // Compraventa
            'Vendedor' => 'vendedor', 'NIT_Vendedor' => 'nit_vendedor', 'Comprador' => 'comprador',
            'NIT_Comprador' => 'nit_comprador', 'Factor' => 'factor', 'NIT_Factor' => 'nit_factor',
            'Nro_Factura' => 'nro_factura', 'Valor' => 'valor', 'Banco' => 'banco',
            'Cuenta_Nro' => 'cuenta_nro',
            // Pagos Compraventa
            'Pago_Ref' => 'pago_ref', 'Concepto' => 'concepto', 'Estado' => 'estado',
            'Fecha_Recaudo' => 'fecha_recaudo', 'Op' => 'op', 'Id_Titulo' => 'id_titulo',
            'Valor_Factura' => 'valor_factura', 'Fec_Inicial' => 'fec_inicial',
            'Fec_Final' => 'fec_final', 'Valor_Descuento' => 'valor_descuento',
            'Capital_Pagado' => 'capital_pagado', 'Total_Pagado' => 'total_pagado',
            'Total_Recaudo' => 'total_recaudo', 'Valor_Recaudado' => 'valor_recaudado',
            'Descuento_Mora_Causado_No_Pagado' => 'descuento_mora_causado_np',
            'Descuento_Mora_Causado_NoPagado'  => 'descuento_mora_causado_np',
            'Rec_Descuento_Mora_Causado_Np'    => 'rec_descuento_mora_np',
            'Rec_Descuento_Mora_Causado_NP'    => 'rec_descuento_mora_np',
            'Rec_Descuento_Mora_Causado_NP_Valor' => 'rec_descuento_mora_np',
            'Saldo_Despues_Pago'      => 'saldo_despues_pago',
            'Saldo_Despues_Del_Pago'  => 'saldo_despues_pago',
        ];

        return array_map(function ($row) use ($explicitMap) {
            $mappedRow = [];
            foreach ($row as $key => $value) {
                if (isset($explicitMap[$key])) {
                    $newKey = $explicitMap[$key];
                } else {
                    $cleanKey = Str::ascii($key);
                    $cleanKey = preg_replace('/([a-z])([A-Z])/', '$1_$2', $cleanKey);
                    $newKey   = strtolower(str_replace('__', '_', $cleanKey));
                }
                $mappedRow[$newKey] = $value;
            }
            return $mappedRow;
        }, $data);
    }

    private function applyConsensus(array $data, string $categoria): array
    {
        if (empty($data)) return $data;

        $keys = [
            'cartera'           => ['name' => 'cliente',  'nit' => 'identificacion'],
            'op'                => ['name' => 'cliente',  'nit' => 'nit_cliente'],
            'pagos'             => ['name' => 'cliente',  'nit' => 'nit'],
            'opf'               => ['name' => 'emisor',   'nit' => 'emisor_nit'],
            'compraventa'       => ['name' => 'vendedor', 'nit' => 'nit_vendedor'],
            'pagos_compraventa' => ['name' => 'pagador',  'nit' => 'nit_pagador'],
        ];

        if (!isset($keys[$categoria])) return $data;
        $nameKey = $keys[$categoria]['name'];
        $nitKey  = $keys[$categoria]['nit'];

        $frequencies = [];
        foreach ($data as $row) {
            if (!isset($row[$nameKey], $row[$nitKey])) continue;
            $name = trim($row[$nameKey]);
            $nit  = preg_replace('/[^0-9]/', '', $row[$nitKey]);
            if ($name === '' || $nit === '') continue;
            $frequencies[$name][$nit] = ($frequencies[$name][$nit] ?? 0) + 1;
        }

        $consensus = [];
        foreach ($frequencies as $name => $nits) {
            arsort($nits);
            $nitList = array_keys($nits);
            $winner  = $nitList[0];
            if (count($nitList) > 1 && $nits[$nitList[0]] === $nits[$nitList[1]]) {
                $masterNit = DB::table('clientes')->where('nombre', $name)->value('identificacion');
                if ($masterNit) {
                    $masterNitClean = preg_replace('/[^0-9]/', '', $masterNit);
                    foreach ($nitList as $tiedNit) {
                        if ($tiedNit === $masterNitClean) { $winner = $tiedNit; break; }
                    }
                }
            }
            $consensus[$name] = $winner;
        }

        foreach ($data as $i => $row) {
            if (isset($row[$nameKey], $consensus[trim($row[$nameKey])])) {
                $data[$i][$nitKey] = $consensus[trim($row[$nameKey])];
            }
        }

        return $data;
    }
}
