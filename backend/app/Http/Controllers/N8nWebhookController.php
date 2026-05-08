<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OperacionCartera;
use App\Models\OperacionFactoring;
use App\Models\PagoFactoring;
use App\Models\OperacionConfirming;
use App\Models\Compraventa;
use App\Models\SystemLog;

class N8nWebhookController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/webhook/n8n/{categoria}",
     *     summary="Recibir datos procesados desde n8n",
     *     description="Endpoint para insertar o actualizar registros masivos procesados por n8n.",
     *     tags={"Webhook"},
     *     @OA\Parameter(
     *         name="categoria",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", enum={"cartera", "op", "pagos", "opf", "compraventa"})
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="filename", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Procesado con éxito")
     * )
     */
    
    public function handle(Request $request, $categoria)
    {
        \Illuminate\Support\Facades\Log::info("Webhook n8n recibido para categoría: {$categoria}");
        \Illuminate\Support\Facades\Log::info("Payload recibido (resumen): " . substr(json_encode($request->all()), 0, 500));

        $data = $request->input('data', []);
        $filename = $request->input('filename') ?? $request->query('filename');
        
        // Si mandan un solo objeto en vez de array, lo convertimos a array.
        if (!is_array(reset($data)) && count($data) > 0) {
            $data = [$data];
        }
        // Extraer filename del array si no vino en el request/query
        if (empty($filename) && is_array($data)) {
            foreach ($data as $row) {
                if (isset($row['filename']) || isset($row['NombreArchivo'])) {
                    $filename = $row['filename'] ?? $row['NombreArchivo'];
                    break;
                }
            }
        }

        try {
            $this->processData($data, $categoria, $filename);

            SystemLog::create([
                'categoria' => $categoria,
                'filename' => $filename,
                'action' => 'Webhook Recibido',
                'message' => 'Lote de datos procesado con éxito.',
                'records_processed' => count($data),
                'payload' => json_encode($data)
            ]);

            return response()->json(['message' => 'Datos procesados correctamente']);
            
        } catch (\Throwable $e) {
            SystemLog::create([
                'categoria' => $categoria,
                'filename' => $filename,
                'action' => 'Error en Webhook',
                'message' => 'Fallo al procesar: ' . $e->getMessage(),
                'records_processed' => 0,
                'payload' => json_encode($data),
                'error_details' => $e->getTraceAsString()
            ]);
            
            return response()->json(['message' => 'Error procesando datos', 'error' => $e->getMessage()], 500);
        }
    }

    public function processData(array $data, string $categoria, ?string $filename = null)
    {
        // 1. Normalize Keys to Match Database Columns (Snake Case) FIRST
        $data = $this->mapKeysToDatabase($data, $categoria);

        // 2. Extraemos 'filename' de los datos y lo quitamos para no insertarlo en los modelos
        foreach ($data as $key => $row) {
            if (isset($row['filename'])) {
                if (empty($filename)) {
                    $filename = $row['filename'];
                }
                unset($data[$key]['filename']);
            }
        }

        // Search for the ClientUpload ID using the filename
        $clientUploadId = null;
        if (!empty($filename)) {
            $clientUploadId = \App\Models\ClientUpload::where('filename', $filename)->value('id');
        }

        // 3. Apply Batch Consensus for Identifiers (Nombre -> NIT)
        $data = $this->applyConsensus($data, $categoria);

        switch ($categoria) {
            case 'cartera':
                foreach ($data as $row) {
                    if (isset($row['actividad_economica'])) {
                        $row['sector_economico'] = \App\Services\IntelligentMapper::mapActivityToSector($row['actividad_economica']);
                    }
                    
                    // Master client data
                    if (isset($row['cliente']) && isset($row['identificacion'])) {
                        $row['identificacion'] = \App\Services\ClientMasterService::masterClient($row['cliente'], $row['identificacion'], [
                            'ciudad' => $row['ciudad'] ?? null,
                            'sector_economico' => $row['sector_economico'] ?? null,
                            'actividad_economica' => $row['actividad_economica'] ?? null,
                        ]);
                    }

                    // Remove only non-existent columns to avoid DB errors
                    $cleanedRow = collect($row)->except(['operacion', 'saldo_total'])->toArray();
                    $cleanedRow['client_upload_id'] = $clientUploadId;
                    OperacionCartera::create($cleanedRow);
                }
                break;
            case 'op':
                foreach ($data as $row) {
                    // Filter out dummy/empty rows (like example row #84)
                    if (empty($row['monto']) || (float)$row['monto'] <= 0) {
                        continue;
                    }

                    if (isset($row['cliente']) && isset($row['nit_cliente'])) {
                        $row['nit_cliente'] = \App\Services\ClientMasterService::masterClient($row['cliente'], $row['nit_cliente']);
                    }

                    // Calculation: intereses_diarios = ((valor_aprobado * tasa_descuento) / 30) / 100
                    $valorAprobado = (float)($row['valor_aprobado'] ?? 0);
                    $tasa = (float)($row['tasa_descuento'] ?? 0);
                    $row['intereses_diarios'] = (($valorAprobado * $tasa) / 30) / 100;
                    $row['client_upload_id'] = $clientUploadId;

                    OperacionFactoring::create($row);
                }
                break;
            case 'pagos':
                foreach ($data as $row) {
                    if (isset($row['cliente']) && isset($row['nit'])) {
                        $row['nit'] = \App\Services\ClientMasterService::masterClient($row['cliente'], $row['nit']);
                    }

                    // Pre-calculation of days if dates are present
                    try {
                        $fPagoStr = $row['fecha_pago'] ?? null;
                        $fFinalStr = $row['fecha_final'] ?? null;
                        $fInicialStr = $row['fecha_inicial'] ?? null;

                        if ($fPagoStr && $fFinalStr) {
                            // Helper to parse dates in d/m/Y or fallback to parse
                            $parseDate = function($dateStr) {
                                if (str_contains($dateStr, '/')) {
                                    return \Carbon\Carbon::createFromFormat('d/m/Y', $dateStr);
                                }
                                return \Carbon\Carbon::parse($dateStr);
                            };

                            $fechaPago = $parseDate($fPagoStr);
                            $fechaFinal = $parseDate($fFinalStr);
                            $fechaInicial = $fInicialStr ? $parseDate($fInicialStr) : null;

                            $row['dias_sobrantes'] = $fechaPago->diffInDays($fechaFinal, false);
                            if ($fechaInicial) {
                                $row['dias_pagos'] = $fechaInicial->diffInDays($fechaPago);
                            }
                        }
                    } catch (\Exception $e) {
                        // Log error if needed: \Log::error("Date parsing failed: " . $e->getMessage());
                    }

                    $row['estado_liquidacion'] = 'pendiente';
                    PagoFactoring::create($row);
                }
                break;
            case 'opf':
                foreach ($data as $row) {
                    if (isset($row['emisor']) && isset($row['emisor_nit'])) {
                        $row['emisor_nit'] = \App\Services\ClientMasterService::masterClient($row['emisor'], $row['emisor_nit']);
                    }
                    OperacionConfirming::create($row);
                }
                break;
            case 'compraventa':
                foreach ($data as $row) {
                    if (isset($row['vendedor']) && isset($row['nit_vendedor'])) {
                        $row['nit_vendedor'] = \App\Services\ClientMasterService::masterClient($row['vendedor'], $row['nit_vendedor']);
                    }
                    Compraventa::create($row);
                }
                break;
            default:
                throw new \Exception('Categoría no válida');
        }
    }

    /**
     * Maps legacy camelCase/Accented keys (from Google Sheets) to the new snake_case database columns.
     */
    private function mapKeysToDatabase(array $data, string $categoria): array
    {
        // Define exact mappings for legacy keys -> new column names
        // Any key not in this map will be transformed to snake_case automatically
        $explicitMap = [
            'Identificación' => 'identificacion',
            'ActividadEconómica' => 'actividad_economica',
            'Operación' => 'operacion',
            'SaldoTotal' => 'saldo_total',
            'PlazoMeses' => 'plazo_meses',
            'TasaInterés' => 'tasa_interes',
            'PlanAmortización' => 'plan_amortizacion',
            'GarantíaDetalle' => 'garantia_detalle',
            'GarantiaDetalle' => 'garantia_detalle',
            'EstadoGarantia' => 'estado_garantia',
            'TipoGarantia' => 'tipo_garantia',
            'FechaDesembolso' => 'fecha_desembolso',
            'NumeroRadicado' => 'numero_radicado',
            'EstadoCapital' => 'estado_capital',
            'FechaVencimientoCapital' => 'fecha_vencimiento_capital',
            'ValorDesembolso' => 'valor_desembolso',
            'SaldoCapital' => 'saldo_capital',
            'Vencido' => 'vencido',
            'DiasVencido' => 'dias_vencido',
            'ValorVencido' => 'valor_vencido',
            'TieneMora' => 'tiene_mora',
            'ValorMora' => 'valor_mora',
            'FechaUltimoAbono' => 'fecha_ultimo_abono',
            'ValorUltimoAbono' => 'valor_ultimo_abono',
            'NombreArchivo' => 'filename',
            'Cliente' => 'cliente',
            'Ciudad' => 'ciudad',
            'Emisor' => 'emisor',
            'Deudor' => 'deudor',
            'Pagador' => 'pagador',
            'Op_relacionada' => 'op_relacionada',
            'Fecha_reliquidacion' => 'fecha_reliquidacion',
            'Valor_Pagar_deudor' => 'valor_pagar_deudor',
            'Emisor_nit' => 'emisor_nit',
            'Deudor_nit' => 'deudor_nit',
            
            // OP specific
            'NIT_Cliente' => 'nit_cliente',
            'Factura_Numero' => 'factura_numero',
            'Monto' => 'monto',
            'Dias' => 'dias',
            'Tasa_Descuento' => 'tasa_descuento',
            'NIT_Pagador' => 'nit_pagador',
            'Fecha_Aprobacion' => 'fecha_aprobacion',
            'Valor_Aprobado' => 'valor_aprobado',
            'Valor_Desembolsado' => 'valor_desembolsado',
            'Fecha_Desembolso' => 'fecha_desembolso',
            'Fecha_Vencimiento' => 'fecha_vencimiento',
            'Valor_Reserva' => 'valor_reserva',
            'Descuento_Financiero' => 'descuento_financiero',
            
            // Pagos specific
            'Pago_Nro' => 'pago_nro',
            'Fecha_Pago' => 'fecha_pago',
            'NIT' => 'nit',
            'Reliquidacion' => 'reliquidacion',
            'Factura_Nro' => 'factura_nro',
            'CC_o_NIT' => 'cc_o_nit',
            'Fecha_Inicial' => 'fecha_inicial',
            'Fecha_Final' => 'fecha_final',
            'Dias_Cartera' => 'dias_cartera',
            'Valor_Titulo' => 'valor_titulo',
            'Valor_Nominal' => 'valor_nominal',
            'Monto_Pagado' => 'monto_pagado',
            'Saldo_Restante' => 'saldo_restante',
            'Total_Recaudado_Comprobante' => 'total_recaudado_comprobante',

            // OPF specfic
            'Tasa_Factor' => 'tasa_factor',
            'ID_Titulo' => 'id_titulo',
            'Reembolso_G_Desembolso' => 'reembolso_g_desembolso',
            'Base_Negociacion' => 'base_negociacion',
            'Rendimientos_Proyectados' => 'rendimientos_proyectados',

            // Compraventa specific
            'Vendedor' => 'vendedor',
            'NIT_Vendedor' => 'nit_vendedor',
            'Comprador' => 'comprador',
            'NIT_Comprador' => 'nit_comprador',
            'Factor' => 'factor',
            'NIT_Factor' => 'nit_factor',
            'Nro_Factura' => 'nro_factura',
            'Valor' => 'valor',
            'Fecha_Vencimiento' => 'fecha_vencimiento',
            'Banco' => 'banco',
            'Cuenta_Nro' => 'cuenta_nro',
        ];

        return array_map(function ($row) use ($explicitMap) {
            $mappedRow = [];
            foreach ($row as $key => $value) {
                if (isset($explicitMap[$key])) {
                    $newKey = $explicitMap[$key];
                } else {
                    // Safe fallback for acronyms like NIT_Cliente -> nit_cliente
                    $cleanKey = \Illuminate\Support\Str::ascii($key);
                    $cleanKey = preg_replace('/([a-z])([A-Z])/', '$1_$2', $cleanKey);
                    $newKey = strtolower(str_replace('__', '_', $cleanKey));
                }
                $mappedRow[$newKey] = $value;
            }
            return $mappedRow;
        }, $data);
    }

    /**
     * Identifies the most frequent NIT for each client name in the batch
     * and normalizes all records to that consensus value.
     */
    private function applyConsensus($data, $categoria)
    {
        if (empty($data)) return $data;

        // Map categories to their respective name & nit keys
        $keys = [
            'cartera' => ['name' => 'cliente', 'nit' => 'identificacion'],
            'op'      => ['name' => 'cliente', 'nit' => 'nit_cliente'],
            'pagos'   => ['name' => 'cliente', 'nit' => 'nit'],
            'opf'     => ['name' => 'emisor',  'nit' => 'emisor_nit'],
            'compraventa' => ['name' => 'vendedor', 'nit' => 'nit_vendedor'],
        ];

        if (!isset($keys[$categoria])) return $data;
        $nameKey = $keys[$categoria]['name'];
        $nitKey = $keys[$categoria]['nit'];

        // 1. Count frequencies of name -> nit mappings
        $frequencies = [];
        foreach ($data as $row) {
            if (isset($row[$nameKey]) && isset($row[$nitKey])) {
                $name = trim($row[$nameKey]);
                $nit = preg_replace('/[^0-9]/', '', $row[$nitKey]);
                if ($name === '' || $nit === '') continue;

                if (!isset($frequencies[$name])) {
                    $frequencies[$name] = [];
                }
                if (!isset($frequencies[$name][$nit])) {
                    $frequencies[$name][$nit] = 0;
                }
                $frequencies[$name][$nit]++;
            }
        }

        // 2. Identify the winner (consensus) for each name
        $consensus = [];
        foreach ($frequencies as $name => $nits) {
            arsort($nits); // Sort by frequency descending
            
            $nitList = array_keys($nits);
            $winner = $nitList[0];
            
            // Si hay un empate en la frecuencia máxima, buscamos en la base de datos maestra
            if (count($nitList) > 1 && $nits[$nitList[0]] === $nits[$nitList[1]]) {
                $masterNit = \Illuminate\Support\Facades\DB::table('clientes')
                    ->where('nombre', $name)
                    ->value('identificacion');
                    
                if ($masterNit) {
                    $masterNitClean = preg_replace('/[^0-9]/', '', $masterNit);
                    foreach ($nitList as $tiedNit) {
                        if ($tiedNit === $masterNitClean) {
                            $winner = $tiedNit;
                            break;
                        }
                    }
                }
            }
            
            $consensus[$name] = $winner;
        }

        // 3. Apply consensus to all rows
        foreach ($data as $i => $row) {
            if (isset($row[$nameKey])) {
                $name = trim($row[$nameKey]);
                if (isset($consensus[$name])) {
                    $data[$i][$nitKey] = $consensus[$name];
                }
            }
        }

        return $data;
    }
}
