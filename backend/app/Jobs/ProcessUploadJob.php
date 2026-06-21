<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\ClientUpload;
use App\Models\SystemLog;
use App\Services\MistralService;
use App\Services\GeminiService;
use App\Http\Controllers\N8nWebhookController;

class ProcessUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 360;

    protected int $uploadId;
    protected string $categoria;

    /**
     * Create a new job instance.
     */
    public function __construct(int $uploadId, string $categoria)
    {
        $this->uploadId = $uploadId;
        $this->categoria = $categoria;
    }

    /**
     * Execute the job.
     */
    public function handle(GeminiService $geminiService, MistralService $mistralService): void
    {
        $upload = ClientUpload::find($this->uploadId);
        if (!$upload) {
            Log::error("ProcessUploadJob failed: ClientUpload ID {$this->uploadId} not found.");
            return;
        }

        Log::info("ProcessUploadJob started for ClientUpload ID: {$this->uploadId}, Categoria: {$this->categoria}");

        $filePath = Storage::path($upload->filename);
        $allRows = [];
        $processedBy = "";

        // 1. PRIMARY: Try processing using Gemini 1.5/2.5 Flash
        try {
            Log::info("Attempting extraction using primary provider: Gemini 1.5 Flash.");
            $upload->update([
                'ocr_status' => 'procesando',
                'ocr_message' => 'Intentando extracción con Gemini...'
            ]);
            $allRows = $this->processWithGemini($filePath, $upload, $geminiService);
            $processedBy = "Gemini 2.5 Flash";
            Log::info("Primary extraction (Gemini) succeeded for ClientUpload ID: {$this->uploadId}");
        } catch (\Throwable $geminiException) {
            Log::warning("Primary provider (Gemini) failed. Error: " . $geminiException->getMessage() . ". Activating fallback: Mistral AI.");

            // 2. FALLBACK: Try processing using Mistral AI (Multi-step OCR + Completions)
            try {
                $upload->update([
                    'ocr_status' => 'procesando',
                    'ocr_message' => 'Gemini falló. Intentando fallback con Mistral AI...'
                ]);
                $allRows = $this->processWithMistral($filePath, $upload, $mistralService);
                $processedBy = "Mistral AI (Fallback)";
                Log::info("Fallback extraction (Mistral) succeeded for ClientUpload ID: {$this->uploadId}");
            } catch (\Throwable $mistralException) {
                Log::error("Both extraction providers (Gemini and Mistral) failed for ClientUpload ID: {$this->uploadId}.");
                
                $upload->update([
                    'ocr_status' => 'fallido',
                    'ocr_message' => 'Fallo al procesar con ambos proveedores: ' . $mistralException->getMessage()
                ]);

                SystemLog::create([
                    'categoria' => $this->categoria,
                    'filename' => $upload->filename,
                    'original_name' => $upload->original_name,
                    'action' => 'Error OCR Dual-Provider',
                    'message' => 'Fallo al procesar con ambos proveedores: ' . $mistralException->getMessage(),
                    'records_processed' => 0,
                    'error_details' => "Gemini: " . $geminiException->getMessage() . "\n\nMistral: " . $mistralException->getMessage() . "\n\n" . $mistralException->getTraceAsString()
                ]);

                throw $mistralException;
            }
        }

        // 3. Persist and Process normalizations via Webhook Controller
        try {
            if (empty($allRows)) {
                throw new \Exception("No records extracted from document.");
            }

            $upload->update([
                'ocr_status' => 'procesando',
                'ocr_message' => "Extracción completada con {$processedBy}. Guardando datos..."
            ]);

            $controller = new N8nWebhookController();
            $controller->processData($allRows, $this->categoria, $upload->filename, $upload->id);

            // Log Success
            SystemLog::create([
                'categoria' => $this->categoria,
                'filename' => $upload->filename,
                'original_name' => $upload->original_name,
                'action' => 'OCR dual-provider processed successfully',
                'message' => "Procesamiento completado con éxito usando {$processedBy}.",
                'records_processed' => count($allRows),
                'payload' => json_encode(array_slice($allRows, 0, 5))
            ]);

            $upload->update([
                'ocr_status' => 'exitoso',
                'ocr_message' => "Procesado con éxito usando {$processedBy}. " . count($allRows) . " registros cargados."
            ]);

            Log::info("ProcessUploadJob finished successfully for ClientUpload ID: {$this->uploadId} using {$processedBy}");

        } catch (\Throwable $e) {
            Log::error("Error persisting data in ProcessUploadJob: " . $e->getMessage());
            
            $upload->update([
                'ocr_status' => 'fallido',
                'ocr_message' => 'Fallo al guardar registros normalizados: ' . $e->getMessage()
            ]);

            SystemLog::create([
                'categoria' => $this->categoria,
                'filename' => $upload->filename,
                'original_name' => $upload->original_name,
                'action' => 'Error OCR Persist',
                'message' => 'Fallo al guardar registros normalizados: ' . $e->getMessage(),
                'records_processed' => 0,
                'error_details' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Primary flow using Gemini 1.5 Flash.
     */
    protected function processWithGemini(string $filePath, ClientUpload $upload, GeminiService $geminiService): array
    {
        $prompt = $this->getGeminiPrompt();
        $response = $geminiService->extractStructuredData($filePath, $upload->original_name, $prompt);

        return $this->normalizeGeminiResponse($response, $upload);
    }

    /**
     * Fallback flow using Mistral AI OCR and Chat Completions.
     */
    protected function processWithMistral(string $filePath, ClientUpload $upload, MistralService $mistralService): array
    {
        $fileId = $mistralService->uploadFile($filePath, $upload->original_name);
        $pages = $mistralService->performOcr($fileId);

        if (empty($pages)) {
            throw new \Exception("Mistral OCR returned no pages.");
        }

        return $this->extractAndParseDataMistral($pages, $mistralService, $upload);
    }

    /**
     * Returns the tailored Gemini prompt instruction for the category.
     */
    protected function getGeminiPrompt(): string
    {
        switch ($this->categoria) {
            case 'cartera':
                return 'Analiza el reporte de cartera y las garantías adjuntas. Extrae la fecha global del reporte (busca "Fecha Consulta:" en formato DD/MM/YYYY) y la lista de todos los desembolsos de todos los clientes.
                Para cada desembolso, genera un objeto en la lista "registros". Si no hay garantías, usa un string vacío.
                Esquema JSON requerido:
                {
                  "fecha_consulta": "DD/MM/YYYY",
                  "registros": [
                    {
                      "Cliente": "Nombre del cliente",
                      "Identificacion": "NIT o CC sin puntos ni guiones",
                      "Ciudad": "Ciudad",
                      "ActividadEconomica": "Actividad económica",
                      "Operacion": "ID de la operación",
                      "SaldoTotal": "Saldo total de la operación",
                      "PlazoMeses": "Plazo en meses",
                      "TasaInteres": "Tasa interés nominal",
                      "PlanAmortizacion": "Plan de amortización",
                      "GarantiaDetalle": "Garantía 1: Tipo - Detalle | Garantía 2: Tipo - Detalle",
                      "EstadoGarantia": "Estado de la garantía principal",
                      "TipoGarantia": "Tipo de la garantía",
                      "FechaDesembolso": "DD/MM/YYYY",
                      "NumeroRadicado": "Número radicado",
                      "EstadoCapital": "Estado capital",
                      "FechaVencimientoCapital": "DD/MM/YYYY",
                      "ValorDesembolso": "Valor del desembolso",
                      "SaldoCapital": "Saldo capital",
                      "Vencido": "Vencido",
                      "DiasVencido": "Días vencido",
                      "ValorVencido": "Valor vencido",
                      "TieneMora": "Tiene mora",
                      "ValorMora": "Valor mora",
                      "FechaUltimoAbono": "DD/MM/YYYY",
                      "ValorUltimoAbono": "Valor del último abono"
                    }
                  ]
                }';

            case 'op':
                return 'Analiza esta Solicitud de Operación de factoring.
                Extrae la fecha de aprobación (busca la fecha en formato DD/MM/YYYY asociada a la operación).
                Esquema JSON requerido:
                {
                  "fecha_aprobacion": "DD/MM/YYYY",
                  "registros": [
                    {
                      "Operacion": "Número de operación",
                      "Cliente": "Nombre cliente",
                      "NIT_Cliente": "NIT cliente sin puntos ni guiones",
                      "Factura_Numero": "Número de factura (doc_nro)",
                      "Monto": "Valor neto de la factura",
                      "Dias": "Días plazo",
                      "Tasa_Descuento": "Tasa de descuento de la operación",
                      "Pagador": "Nombre pagador",
                      "NIT_Pagador": "NIT pagador sin puntos ni guiones",
                      "Valor_Aprobado": "Valor aprobado / presente",
                      "Valor_Desembolsado": "Valor neto a entregar de la liquidación general",
                      "Fecha_Desembolso": "Fecha inicial/desembolso de la factura",
                      "Fecha_Vencimiento": "Fecha de vencimiento de la factura",
                      "Valor_Reserva": "Valor de la reserva",
                      "Descuento_Financiero": "Descuento financiero de esta factura"
                    }
                  ]
                }';

            case 'pagos':
                return 'Analiza el comprobante de Pagos Clientes.
                Esquema JSON requerido:
                {
                  "registros": [
                    {
                      "Pago_Nro": "Número de pago (PAGO N°.)",
                      "Fecha_Pago": "Fecha de pago (DD/MM/YYYY)",
                      "Cliente": "Nombre cliente",
                      "Nit": "NIT cliente sin puntos ni guiones",
                      "Reliquidacion": "Reliquidación",
                      "Fecha_Reliquidacion": "Fecha reliquidación (DD/MM/YYYY)",
                      "OP_Relacionada": "Operación relacionada (op_nro)",
                      "Factura_Nro": "Número de factura (doc_nro)",
                      "CC_o_NIT": "CC o NIT del pagador",
                      "Pagador": "Nombre pagador",
                      "Fecha_Inicial": "Fecha inicial de la factura",
                      "Fecha_Final": "Fecha final de la factura",
                      "Dias_Cartera": "Días",
                      "Valor_Titulo": "Valor total del título",
                      "Valor_Nominal": "Valor nominal",
                      "Descuento_Financiero": "Descuento financiero",
                      "Monto_Pagado": "Valor pagado",
                      "Saldo_Restante": "Saldo restante",
                      "Total_Recaudado_Comprobante": "Valor recaudado total del comprobante"
                    }
                  ]
                }';

            case 'opf':
                return 'Analiza el documento de Confirming / Pago a Proveedores.
                Excluye filas de totales, subtotales o sumatorias. Cada registro debe corresponder a un título individual.
                Esquema JSON requerido:
                {
                  "registros": [
                    {
                      "Operacion": "Número de operación (OPERACION N°.)",
                      "Emisor": "Nombre emisor",
                      "Emisor_Nit": "NIT emisor sin puntos ni guiones",
                      "Deudor": "Nombre deudor",
                      "Deudor_Nit": "NIT deudor sin puntos ni guiones",
                      "Tasa_Factor": "Factor de descuento",
                      "ID_Titulo": "ID del título",
                      "Fecha_Inicial": "Fecha inicial (DD/MM/YYYY)",
                      "Fecha_Final": "Fecha final (DD/MM/YYYY)",
                      "Dias": "Días",
                      "Valor_Nominal": "Valor nominal",
                      "Reembolso_G_Desembolso": "Reembolso G Desembolso",
                      "Base_Negociacion": "Base negociación",
                      "Rendimientos_Proyectados": "Rendimientos proyectados",
                      "Valor_Pagar_Deudor": "Valor a pagar deudor"
                    }
                  ]
                }';

            case 'compraventa':
                return 'Analiza la Notificación de Garantía Mobiliaria, Endoso y Cesión de Derechos Económicos.
                Esquema JSON requerido:
                {
                  "registros": [
                    {
                      "Vendedor": "Nombre del vendedor",
                      "NIT_Vendedor": "NIT vendedor sin puntos ni guiones",
                      "Comprador": "Nombre del comprador",
                      "NIT_Comprador": "NIT comprador sin puntos ni guiones",
                      "Factor": "Nombre del factor",
                      "NIT_Factor": "NIT factor sin puntos ni guiones",
                      "Nro_Factura": "Número de factura",
                      "Valor": "Valor de la factura",
                      "Fecha_Vencimiento": "Fecha de vencimiento (DD/MM/YYYY)",
                      "Banco": "Banco para el pago",
                      "Cuenta_Nro": "Número de cuenta"
                    }
                  ]
                }';

            case 'pagos_compraventa':
                return 'Analiza el documento de Pagos Compra Venta Pagador de Proseguir. Extrae la información de cabecera y cada fila de la liquidación.
                Esquema JSON requerido:
                {
                  "pago_ref": "Pago Ref exacto a la derecha de PAGO Nº. (ej: REF-1-95)",
                  "pagador": "Nombre del pagador",
                  "nit_pagador": "NIT pagador",
                  "cliente": "Nombre del cliente",
                  "nit_cliente": "NIT cliente",
                  "concepto": "Concepto de pago",
                  "estado": "Estado del pago",
                  "fecha_recaudo": "Fecha de recaudo (DD/MM/YYYY)",
                  "total_recaudo": "Valor total recaudo",
                  "valor_recaudado": "Valor recaudado",
                  "registros": [
                    {
                      "Op": "Número de operación (Op#)",
                      "Id_Titulo": "ID del título (IdTitulo)",
                      "Valor_Factura": "Valor factura",
                      "Fec_Inicial": "Fecha inicial (DD/MM/YYYY)",
                      "Fec_Final": "Fecha final (DD/MM/YYYY)",
                      "Dias": "Días",
                      "Factor": "Factor",
                      "Saldo_Capital": "Saldo capital",
                      "Valor_Descuento": "Valor descuento",
                      "Capital_Pagado": "Capital pagado",
                      "Descuento_Mora_Causado_No_Pagado": "Descuento/Mora Causado No Pagado",
                      "Rec_Descuento_Mora_Causado_Np": "Rec. Descuento/Mora Causado NP",
                      "Total_Pagado": "Total pagado",
                      "Saldo_Despues_Pago": "Saldo después del pago"
                    }
                  ]
                }';

            default:
                throw new \Exception("Categoría no soportada para Gemini: {$this->categoria}");
        }
    }

    /**
     * Normalizes and cleans the JSON schema returned by Gemini to fit database requirements.
     */
    protected function normalizeGeminiResponse(array $response, ClientUpload $upload): array
    {
        $registros = $response['registros'] ?? $response;
        if (!is_array($registros)) {
            throw new \Exception("Invalid structured response from Gemini: 'registros' list is missing.");
        }

        $allRows = [];

        switch ($this->categoria) {
            case 'cartera':
                $fechaReporte = $response['fecha_consulta'] ?? "01/01/2026";
                $partes = explode('/', $fechaReporte);
                $mes = $partes[1] ?? "01";
                $anio = $partes[2] ?? "2026";

                foreach ($registros as $row) {
                    $allRows[] = [
                        'Cliente' => $row['Cliente'] ?? null,
                        'Identificación' => $row['Identificacion'] ?? null,
                        'Ciudad' => $row['Ciudad'] ?? null,
                        'ActividadEconómica' => $row['ActividadEconomica'] ?? null,
                        'Operación' => $row['Operacion'] ?? "N/A",
                        'SaldoTotal' => $this->limpiarNumero($row['SaldoTotal'] ?? 0),
                        'PlazoMeses' => $this->limpiarNumero($row['PlazoMeses'] ?? 0),
                        'TasaInterés' => $this->limpiarNumero($row['TasaInteres'] ?? 0),
                        'PlanAmortización' => $row['PlanAmortizacion'] ?? "",
                        'GarantiaDetalle' => $row['GarantiaDetalle'] ?? "",
                        'EstadoGarantia' => $row['EstadoGarantia'] ?? null,
                        'TipoGarantia' => $row['TipoGarantia'] ?? null,
                        'FechaDesembolso' => $row['FechaDesembolso'] ?? null,
                        'NumeroRadicado' => $row['NumeroRadicado'] ?? null,
                        'EstadoCapital' => $row['EstadoCapital'] ?? null,
                        'FechaVencimientoCapital' => $row['FechaVencimientoCapital'] ?? null,
                        'ValorDesembolso' => $this->limpiarNumero($row['ValorDesembolso'] ?? 0),
                        'SaldoCapital' => $this->limpiarNumero($row['SaldoCapital'] ?? 0),
                        'Vencido' => $row['Vencido'] ?? null,
                        'DiasVencido' => $this->limpiarNumero($row['DiasVencido'] ?? 0),
                        'ValorVencido' => $this->limpiarNumero($row['ValorVencido'] ?? 0),
                        'TieneMora' => $row['TieneMora'] ?? null,
                        'ValorMora' => $this->limpiarNumero($row['ValorMora'] ?? 0),
                        'FechaUltimoAbono' => $row['FechaUltimoAbono'] ?? null,
                        'ValorUltimoAbono' => $this->limpiarNumero($row['ValorUltimoAbono'] ?? 0),
                        'NombreArchivo' => "Cartera_{$mes}_{$anio}.xlsx"
                    ];
                }

                // Sort alphabetically by Cliente
                usort($allRows, function ($a, $b) {
                    return strcasecmp($a['Cliente'] ?? '', $b['Cliente'] ?? '');
                });
                break;

            case 'op':
                $fechaOperacion = $response['fecha_aprobacion'] ?? "01/01/2026";
                foreach ($registros as $row) {
                    $operacionNro = $row['Operacion'] ?? "N/A";
                    $allRows[] = [
                        'Operacion' => $operacionNro,
                        'Cliente' => $row['Cliente'] ?? "N/A",
                        'NIT_Cliente' => $row['NIT_Cliente'] ?? "N/A",
                        'Factura_Numero' => $row['Factura_Numero'] ?? null,
                        'Monto' => $this->limpiarNumero($row['Monto'] ?? 0),
                        'Dias' => $this->limpiarNumero($row['Dias'] ?? 0),
                        'Tasa_Descuento' => $this->limpiarNumero($row['Tasa_Descuento'] ?? 0),
                        'Pagador' => $row['Pagador'] ?? "N/A",
                        'NIT_Pagador' => $row['NIT_Pagador'] ?? "N/A",
                        'Fecha_Aprobacion' => $fechaOperacion,
                        'Valor_Aprobado' => $this->limpiarNumero($row['Valor_Aprobado'] ?? 0),
                        'Valor_Desembolsado' => $this->limpiarNumero($row['Valor_Desembolsado'] ?? 0),
                        'Fecha_Desembolso' => $row['Fecha_Desembolso'] ?? null,
                        'Fecha_Vencimiento' => $row['Fecha_Vencimiento'] ?? null,
                        'Valor_Reserva' => $this->limpiarNumero($row['Valor_Reserva'] ?? 0),
                        'Descuento_Financiero' => $this->limpiarNumero($row['Descuento_Financiero'] ?? 0),
                        'NombreArchivo' => "Factoring_OP_{$operacionNro}.xlsx"
                    ];
                }
                break;

            case 'pagos':
                foreach ($registros as $row) {
                    $pagoNro = $row['Pago_Nro'] ?? "N/A";
                    $allRows[] = [
                        'Pago_Nro' => $pagoNro,
                        'Fecha_Pago' => $row['Fecha_Pago'] ?? null,
                        'Cliente' => $row['Cliente'] ?? null,
                        'Nit' => $row['Nit'] ?? null,
                        'Reliquidacion' => $row['Reliquidacion'] ?? null,
                        'Fecha_Reliquidacion' => $row['Fecha_Reliquidacion'] ?? null,
                        'OP_Relacionada' => $row['OP_Relacionada'] ?? null,
                        'Factura_Nro' => $row['Factura_Nro'] ?? null,
                        'CC_o_NIT' => $row['CC_o_NIT'] ?? null,
                        'Pagador' => $row['Pagador'] ?? null,
                        'Fecha_Inicial' => $row['Fecha_Inicial'] ?? null,
                        'Fecha_Final' => $row['Fecha_Final'] ?? null,
                        'Dias_Cartera' => $this->limpiarNumero($row['Dias_Cartera'] ?? 0),
                        'Valor_Titulo' => $this->limpiarNumero($row['Valor_Titulo'] ?? 0),
                        'Valor_Nominal' => $this->limpiarNumero($row['Valor_Nominal'] ?? 0),
                        'Descuento_Financiero' => $this->limpiarNumero($row['Descuento_Financiero'] ?? 0),
                        'Monto_Pagado' => $this->limpiarNumero($row['Monto_Pagado'] ?? 0),
                        'Saldo_Restante' => $this->limpiarNumero($row['Saldo_Restante'] ?? 0),
                        'Total_Recaudado_Comprobante' => $this->limpiarNumero($row['Total_Recaudado_Comprobante'] ?? 0),
                        'NombreArchivo' => "Pagos_Factoring_{$pagoNro}.xlsx"
                    ];
                }
                break;

            case 'opf':
                foreach ($registros as $row) {
                    $opNro = $row['Operacion'] ?? "N/A";
                    $allRows[] = [
                        'Operacion' => $opNro,
                        'Emisor' => $row['Emisor'] ?? null,
                        'Emisor_Nit' => $row['Emisor_Nit'] ?? null,
                        'Deudor' => $row['Deudor'] ?? null,
                        'Deudor_Nit' => $row['Deudor_Nit'] ?? null,
                        'Tasa_Factor' => $this->limpiarNumero($row['Tasa_Factor'] ?? 0),
                        'ID_Titulo' => $row['ID_Titulo'] ?? null,
                        'Fecha_Inicial' => $row['Fecha_Inicial'] ?? null,
                        'Fecha_Final' => $row['Fecha_Final'] ?? null,
                        'Dias' => $this->limpiarNumero($row['Dias'] ?? 0),
                        'Valor_Nominal' => $this->limpiarNumero($row['Valor_Nominal'] ?? 0),
                        'Reembolso_G_Desembolso' => $this->limpiarNumero($row['Reembolso_G_Desembolso'] ?? 0),
                        'Base_Negociacion' => $this->limpiarNumero($row['Base_Negociacion'] ?? 0),
                        'Rendimientos_Proyectados' => $this->limpiarNumero($row['Rendimientos_Proyectados'] ?? 0),
                        'Valor_Pagar_Deudor' => $this->limpiarNumero($row['Valor_Pagar_Deudor'] ?? 0),
                        'NombreArchivo' => "Confirming_{$opNro}.xlsx"
                    ];
                }
                break;

            case 'compraventa':
                foreach ($registros as $row) {
                    $vendedor = $row['Vendedor'] ?? "N/A";
                    $allRows[] = [
                        'Vendedor' => $vendedor,
                        'NIT_Vendedor' => $row['NIT_Vendedor'] ?? "N/A",
                        'Comprador' => $row['Comprador'] ?? "N/A",
                        'NIT_Comprador' => $row['NIT_Comprador'] ?? "N/A",
                        'Factor' => $row['Factor'] ?? "N/A",
                        'NIT_Factor' => $row['NIT_Factor'] ?? "N/A",
                        'Nro_Factura' => $row['Nro_Factura'] ?? null,
                        'Valor' => $this->limpiarNumero($row['Valor'] ?? 0),
                        'Fecha_Vencimiento' => $row['Fecha_Vencimiento'] ?? null,
                        'Banco' => $row['Banco'] ?? null,
                        'Cuenta_Nro' => $row['Cuenta_Nro'] ?? null,
                        'NombreArchivo' => "Compraventa_" . substr($vendedor, 0, 10) . ".xlsx"
                    ];
                }
                break;

            case 'pagos_compraventa':
                $pagoRef = $response['pago_ref'] ?? '';
                $pagador = $response['pagador'] ?? '';
                $pagadorNit = $response['nit_pagador'] ?? '';
                $cliente = $response['cliente'] ?? '';
                $clienteNit = $response['nit_cliente'] ?? '';
                $concepto = $response['concepto'] ?? '';
                $estado = $response['estado'] ?? '';
                $fechaRecaudo = $response['fecha_recaudo'] ?? '';
                $totalRecaudo = $this->limpiarNumero($response['total_recaudo'] ?? 0);
                $valorRecaudado = $this->limpiarNumero($response['valor_recaudado'] ?? 0);

                foreach ($registros as $row) {
                    $allRows[] = [
                        'filename' => $upload->filename,
                        'Pago_Ref' => $pagoRef,
                        'Pagador' => $pagador,
                        'NIT_Pagador' => $pagadorNit,
                        'Cliente' => $cliente,
                        'NIT_Cliente' => $clienteNit,
                        'Concepto' => $concepto,
                        'Estado' => $estado,
                        'Fecha_Recaudo' => $fechaRecaudo,
                        'Op' => $row['Op'] ?? '',
                        'Id_Titulo' => $row['Id_Titulo'] ?? '',
                        'Valor_Factura' => $this->limpiarNumero($row['Valor_Factura'] ?? 0),
                        'Fec_Inicial' => $row['Fec_Inicial'] ?? '',
                        'Fec_Final' => $row['Fec_Final'] ?? '',
                        'Dias' => $row['Dias'] ?? '',
                        'Factor' => $row['Factor'] ?? '',
                        'Saldo_Capital' => $this->limpiarNumero($row['Saldo_Capital'] ?? 0),
                        'Valor_Descuento' => $this->limpiarNumero($row['Valor_Descuento'] ?? 0),
                        'Capital_Pagado' => $this->limpiarNumero($row['Capital_Pagado'] ?? 0),
                        'Descuento_Mora_Causado_No_Pagado' => $this->limpiarNumero($row['Descuento_Mora_Causado_No_Pagado'] ?? 0),
                        'Rec_Descuento_Mora_Causado_Np' => $this->limpiarNumero($row['Rec_Descuento_Mora_Causado_Np'] ?? 0),
                        'Total_Pagado' => $this->limpiarNumero($row['Total_Pagado'] ?? 0),
                        'Saldo_Despues_Pago' => $this->limpiarNumero($row['Saldo_Despues_Pago'] ?? 0),
                        'Total_Recaudo' => $totalRecaudo,
                        'Valor_Recaudado' => $valorRecaudado
                    ];
                }
                break;
        }

        return $allRows;
    }

    /**
     * Fallback parsing logic from pages generated by Mistral OCR.
     */
    protected function extractAndParseDataMistral(array $pages, MistralService $mistralService, ClientUpload $upload): array
    {
        $allRows = [];

        switch ($this->categoria) {
            case 'cartera':
                // Capture Global Report Date
                $fechaReporte = "01/01/2026";
                if (isset($pages[0]['markdown']) && preg_match('/Fecha Consulta:\s*(\d{2}\/\d{2}\/\d{4})/', $pages[0]['markdown'], $matches)) {
                    $fechaReporte = $matches[1];
                }

                $prompt = 'Extrae la información completa del siguiente texto y devuélvela estrictamente en formato JSON. Incluye todos los campos disponibles sin omitir ninguno. Si un campo no tiene valor, usa null. Esquema requerido: { "cliente": { "nombre": "", "id": "", "ciudad": "", "actividad_economica": "" }, "operacion": { "id": "", "saldo_total": "", "plazo_meses": "", "tasa_interes_nm": "", "plan_amortizacion": "" }, "garantia": { "estado": "", "tipo": "", "valor_avaluo": "", "detalle": "" }, "detalle_financiero": { "fecha_desembolso": "", "nro_radicado": "", "estado_capital": "", "fecha_vencimiento_capital": "", "valor_desembolso": "", "saldo_capital": "", "vencido": "", "dias_vencido": "", "valor_vencido": "", "tiene_mora": "", "valor_mora": "", "fecha_ultimo_abono": "", "valor_ultimo_abono": "" } }';

                foreach ($pages as $page) {
                    if (empty($page['markdown'])) continue;
                    $data = $mistralService->getStructuredExtraction($prompt, $page['markdown']);

                    if (empty($data['cliente']) || empty($data['detalle_financiero'])) continue;

                    $detalleUnificado = "";
                    if (!empty($data['garantia'])) {
                        $listaGarantias = is_array($data['garantia']) && !\Illuminate\Support\Arr::isAssoc($data['garantia']) ? $data['garantia'] : [$data['garantia']];
                        $garantiasText = [];
                        foreach ($listaGarantias as $i => $g) {
                            $tipo = $g['tipo'] ?? '';
                            $det = $g['detalle'] ?? '';
                            $garantiasText[] = "Garantía " . ($i + 1) . ": {$tipo} - {$det}";
                        }
                        $detalleUnificado = implode(' | ', $garantiasText);
                    }

                    $detalles = is_array($data['detalle_financiero']) && !\Illuminate\Support\Arr::isAssoc($data['detalle_financiero']) ? $data['detalle_financiero'] : [$data['detalle_financiero']];

                    $partes = explode('/', $fechaReporte);
                    $mes = $partes[1] ?? "01";
                    $anio = $partes[2] ?? "2026";

                    foreach ($detalles as $desembolso) {
                        $estadoG = null;
                        $tipoG = null;
                        if (!empty($data['garantia'])) {
                            if (is_array($data['garantia']) && !\Illuminate\Support\Arr::isAssoc($data['garantia'])) {
                                $estadoG = $data['garantia'][0]['estado'] ?? null;
                                $tipoG = $data['garantia'][0]['tipo'] ?? null;
                            } else {
                                $estadoG = $data['garantia']['estado'] ?? null;
                                $tipoG = $data['garantia']['tipo'] ?? null;
                            }
                        }

                        $allRows[] = [
                            'Cliente' => $data['cliente']['nombre'] ?? null,
                            'Identificación' => $data['cliente']['id'] ?? null,
                            'Ciudad' => $data['cliente']['ciudad'] ?? null,
                            'ActividadEconómica' => $data['cliente']['actividad_economica'] ?? null,
                            'Operación' => $data['operacion']['id'] ?? "N/A",
                            'SaldoTotal' => $this->limpiarNumero($data['operacion']['saldo_total'] ?? 0),
                            'PlazoMeses' => $this->limpiarNumero($data['operacion']['plazo_meses'] ?? 0),
                            'TasaInterés' => $this->limpiarNumero($data['operacion']['tasa_interes_nm'] ?? 0),
                            'PlanAmortización' => $data['operacion']['plan_amortizacion'] ?? "",
                            'GarantiaDetalle' => $detalleUnificado,
                            'EstadoGarantia' => $estadoG,
                            'TipoGarantia' => $tipoG,
                            'FechaDesembolso' => $desembolso['fecha_desembolso'] ?? null,
                            'NumeroRadicado' => $desembolso['nro_radicado'] ?? null,
                            'EstadoCapital' => $desembolso['estado_capital'] ?? null,
                            'FechaVencimientoCapital' => $desembolso['fecha_vencimiento_capital'] ?? null,
                            'ValorDesembolso' => $this->limpiarNumero($desembolso['valor_desembolso'] ?? 0),
                            'SaldoCapital' => $this->limpiarNumero($desembolso['saldo_capital'] ?? 0),
                            'Vencido' => $desembolso['vencido'] ?? null,
                            'DiasVencido' => $this->limpiarNumero($desembolso['dias_vencido'] ?? 0),
                            'ValorVencido' => $this->limpiarNumero($desembolso['valor_vencido'] ?? 0),
                            'TieneMora' => $desembolso['tiene_mora'] ?? null,
                            'ValorMora' => $this->limpiarNumero($desembolso['valor_mora'] ?? 0),
                            'FechaUltimoAbono' => $desembolso['fecha_ultimo_abono'] ?? null,
                            'ValorUltimoAbono' => $this->limpiarNumero($desembolso['valor_ultimo_abono'] ?? 0),
                            'NombreArchivo' => "Cartera_{$mes}_{$anio}.xlsx"
                        ];
                    }
                }

                // Sort alphabetically by Cliente name
                usort($allRows, function ($a, $b) {
                    return strcasecmp($a['Cliente'] ?? '', $b['Cliente'] ?? '');
                });
                break;

            case 'op':
                // Capture Date
                $fechaOperacion = "01/01/2026";
                if (isset($pages[0]['markdown']) && preg_match('/\|\s*(\d{2}\/\d{2}\/\d{4})/', $pages[0]['markdown'], $matches)) {
                    $fechaOperacion = $matches[1];
                }

                $prompt = "Analiza esta Solicitud de Operación. El OCR tiene un error de segmentación: la tabla de facturas se corta en 'Vr. Reserva' y los valores de 'Descuento Financiero' aparecen como líneas de texto sueltas justo después.\n\nINSTRUCCIONES DE EXTRACCIÓN DINÁMICA:\n1. IDENTIFICACIÓN DE FILAS: Extrae cada factura manteniendo los 11 campos. \n2. RECONSTRUCCIÓN DE COLUMNA: Para el campo 'descuento_financiero', busca los valores numéricos que el OCR extrajo como saltos de línea (`\n`) o texto independiente fuera de los separadores '|'. \n3. ASIGNACIÓN: El primer valor suelto corresponde a la primera factura, el segundo a la segunda, y así sucesivamente. No asumas que el valor está en la liquidación; búscalo en el cuerpo del texto.\n\nJSON REQUERIDO:\n{\n  \"operacion\": {\n    \"nro\": \"\",\n    \"fecha_operacion\": \"\",\n    \"tasa_descuento\": \"\",\n    \"cliente\": { \"nombre\": \"\", \"nit\": \"\" },\n    \"pagador\": { \"nombre\": \"\", \"nit\": \"\" }\n  },\n  \"detalle_facturas\": [\n    {\n      \"doc_nro\": \"\",\n      \"dias\": \"\",\n      \"fecha_inicial\": \"\",\n      \"fecha_vencimiento\": \"\",\n      \"valor_neto\": \"\",\n      \"valor_presente\": \"\",\n      \"valor_reserva\": \"\",\n      \"descuento_financiero\": \"\"\n    }\n  ],\n  \"liquidacion\": { \"valor_neto_entregar\": \"\" }\n}";

                foreach ($pages as $page) {
                    if (empty($page['markdown'])) continue;
                    $data = $mistralService->getStructuredExtraction($prompt, $page['markdown']);

                    $operacionNro = $data['operacion']['nro'] ?? "N/A";
                    $tasaDescuento = $this->limpiarNumero($data['operacion']['tasa_descuento'] ?? 0);
                    $cliente = $data['operacion']['cliente']['nombre'] ?? "N/A";
                    $clienteNit = $data['operacion']['cliente']['nit'] ?? "N/A";
                    $pagador = $data['operacion']['pagador']['nombre'] ?? "N/A";
                    $pagadorNit = $data['operacion']['pagador']['nit'] ?? "N/A";
                    $netoEntregar = $this->limpiarNumero($data['liquidacion']['valor_neto_entregar'] ?? 0);

                    if (!empty($data['detalle_facturas']) && is_array($data['detalle_facturas'])) {
                        foreach ($data['detalle_facturas'] as $fac) {
                            $allRows[] = [
                                'Operacion' => $operacionNro,
                                'Cliente' => $cliente,
                                'NIT_Cliente' => $clienteNit,
                                'Factura_Numero' => $fac['doc_nro'] ?? null,
                                'Monto' => $this->limpiarNumero($fac['valor_neto'] ?? 0),
                                'Dias' => $this->limpiarNumero($fac['dias'] ?? 0),
                                'Tasa_Descuento' => $tasaDescuento,
                                'Pagador' => $pagador,
                                'NIT_Pagador' => $pagadorNit,
                                'Fecha_Aprobacion' => $fechaOperacion,
                                'Valor_Aprobado' => $this->limpiarNumero($fac['valor_presente'] ?? 0),
                                'Valor_Desembolsado' => $netoEntregar,
                                'Fecha_Desembolso' => $fac['fecha_inicial'] ?? null,
                                'Fecha_Vencimiento' => $fac['fecha_vencimiento'] ?? null,
                                'Valor_Reserva' => $this->limpiarNumero($fac['valor_reserva'] ?? 0),
                                'Descuento_Financiero' => $this->limpiarNumero($fac['descuento_financiero'] ?? 0),
                                'NombreArchivo' => "Factoring_OP_{$operacionNro}.xlsx"
                            ];
                        }
                    }
                }
                break;

            case 'pagos':
                $prompt = "Analiza este documento de 'PAGOS CLIENTES'. Extrae la información detallada de la liquidación y el recaudo.\n\nINSTRUCCIONES DE EXTRACCIÓN:\n1. GENERAL: Extrae el número de pago (PAGO N°.), el cliente, su NIT y la fecha de pago.\n2. TABLA DE LIQUIDACIÓN: Extrae cada fila de la tabla 'Liquidación a fecha pago'. Debes incluir:\n   - op_nro, doc_nro, pagador, dias, vr_total_titulo, vr_nominal, descuento_financiero, vr_pagado y saldo.\n3. DETALLE RECAUDO: Extrae el 'Valor Recaudado' y el 'Total Devolución'.\n\nJSON REQUERIDO:\n{\n  \"pago\": {\n    \"nro\": \"\",\n    \"fecha\": \"\",\n    \"cliente\": { \"nombre\": \"\", \"nit\": \"\", \"reliquidacion\": \"\", \"fecha_reliquidacion\": \"\" }\n  },\n  \"detalle_facturas\": [\n    {\n      \"op_nro\": \"\",\n      \"doc_nro\": \"\",\n      \"pagador\": \"\",\n      \"cc_o_nit\": \"\",\n      \"fecha_inicial\": \"\",\n      \"fecha_final\": \"\",\n      \"dias\": \"\",\n      \"vr_total_titulo\": \"\",\n      \"vr_nominal\": \"\",\n      \"descuento_financiero\": \"\",\n      \"vr_pagado\": \"\",\n      \"saldo\": \"\"\n    }\n  ],\n  \"recaudo\": {\n    \"valor_recaudado\": \"\",\n    \"total_devolucion\": \"\"\n  }\n}";

                foreach ($pages as $page) {
                    if (empty($page['markdown'])) continue;
                    $data = $mistralService->getStructuredExtraction($prompt, $page['markdown']);

                    $pagoNro = $data['pago']['nro'] ?? null;
                    $fechaPago = $data['pago']['fecha'] ?? null;
                    $cliente = $data['pago']['cliente']['nombre'] ?? null;
                    $nit = $data['pago']['cliente']['nit'] ?? null;
                    $reliquidacion = $data['pago']['cliente']['reliquidacion'] ?? null;
                    $fechaReliquidacion = $data['pago']['cliente']['fecha_reliquidacion'] ?? null;
                    $valorRecaudado = $this->limpiarNumero($data['recaudo']['valor_recaudado'] ?? 0);

                    if (!empty($data['detalle_facturas']) && is_array($data['detalle_facturas'])) {
                        foreach ($data['detalle_facturas'] as $fac) {
                            $allRows[] = [
                                'Pago_Nro' => $pagoNro,
                                'Fecha_Pago' => $fechaPago,
                                'Cliente' => $cliente,
                                'Nit' => $nit,
                                'Reliquidacion' => $reliquidacion,
                                'Fecha_Reliquidacion' => $fechaReliquidacion,
                                'OP_Relacionada' => $fac['op_nro'] ?? null,
                                'Factura_Nro' => $fac['doc_nro'] ?? null,
                                'CC_o_NIT' => $fac['cc_o_nit'] ?? null,
                                'Pagador' => $fac['pagador'] ?? null,
                                'Fecha_Inicial' => $fac['fecha_inicial'] ?? null,
                                'Fecha_Final' => $fac['fecha_final'] ?? null,
                                'Dias_Cartera' => $this->limpiarNumero($fac['dias'] ?? 0),
                                'Valor_Titulo' => $this->limpiarNumero($fac['vr_total_titulo'] ?? 0),
                                'Valor_Nominal' => $this->limpiarNumero($fac['vr_nominal'] ?? 0),
                                'Descuento_Financiero' => $this->limpiarNumero($fac['descuento_financiero'] ?? 0),
                                'Monto_Pagado' => $this->limpiarNumero($fac['vr_pagado'] ?? 0),
                                'Saldo_Restante' => $this->limpiarNumero($fac['saldo'] ?? 0),
                                'Total_Recaudado_Comprobante' => $valorRecaudado,
                                'NombreArchivo' => "Pagos_Factoring_{$pagoNro}.xlsx"
                            ];
                        }
                    }
                }
                break;

            case 'opf':
                $prompt = "Analiza este documento de 'CONFIRMING-PAGO PROVEEDORES'. Extrae la información técnica y los títulos.\n\nINSTRUCCIONES CRÍTICAS DE FILTRADO:\n1. TABLA DE TÍTULOS: Extrae solo los registros individuales de títulos. \n2. EXCLUSIÓN: Ignora y NO extraigas las filas que digan 'Total:', 'Subtotal' o que contengan sumatorias de las columnas. Solo queremos los datos de cada factura/título por separado.\n3. VALIDACIÓN: Cada objeto en 'detalle_titulos' debe tener un 'id_titulo' numérico real.\n\nINSTRUCCIONES DE EXTRACCIÓN:\n1. ENCABEZADO: Extrae el número de operación (OPERACION N°.), Emisor, Deudor y NITs.\n2. TASA: 'FACTOR DE DESCUENTO' (ej: 1.80% n.m.v.).\n3. CAMPOS POR TÍTULO: id_titulo, fecha_inicial, fecha_final, dias, vr_nominal, reembolso_g_desembolso, base_negociacion, rend_proyectados y valor_pagar_deudor.\n\nJSON REQUERIDO:\n{\n  \"operacion\": {\n    \"nro\": \"\",\n    \"fecha\": \"\",\n    \"factor_descuento\": \"\",\n    \"emisor\": { \"nombre\": \"\", \"nit\": \"\" },\n    \"deudor\": { \"nombre\": \"\", \"nit\": \"\" }\n  },\n  \"detalle_titulos\": [\n    {\n      \"id_titulo\": \"\",\n      \"fecha_inicial\": \"\",\n      \"fecha_final\": \"\",\n      \"dias\": \"\",\n      \"vr_nominal\": \"\",\n      \"reembolso_g_desembolso\": \"\",\n      \"base_negociacion\": \"\",\n      \"rend_proyectados\": \"\",\n      \"valor_pagar_deudor\": \"\"\n    }\n  ],\n  \"liquidacion\": {\n    \"valor_neto_entregar\": \"\"\n  }\n}";

                foreach ($pages as $page) {
                    if (empty($page['markdown'])) continue;
                    $data = $mistralService->getStructuredExtraction($prompt, $page['markdown']);

                    $opNro = $data['operacion']['nro'] ?? null;
                    $emisor = $data['operacion']['emisor']['nombre'] ?? null;
                    $deudor = $data['operacion']['deudor']['nombre'] ?? null;
                    $emisorNit = $data['operacion']['emisor']['nit'] ?? null;
                    $deudorNit = $data['operacion']['deudor']['nit'] ?? null;
                    $factor = $this->limpiarNumero($data['operacion']['factor_descuento'] ?? 0);

                    if (!empty($data['detalle_titulos']) && is_array($data['detalle_titulos'])) {
                        foreach ($data['detalle_titulos'] as $titulo) {
                            $allRows[] = [
                                'Operacion' => $opNro,
                                'Emisor' => $emisor,
                                'Emisor_Nit' => $emisorNit,
                                'Deudor' => $deudor,
                                'Deudor_Nit' => $deudorNit,
                                'Tasa_Factor' => $factor,
                                'ID_Titulo' => $titulo['id_titulo'] ?? null,
                                'Fecha_Inicial' => $titulo['fecha_inicial'] ?? null,
                                'Fecha_Final' => $titulo['fecha_final'] ?? null,
                                'Dias' => $this->limpiarNumero($titulo['dias'] ?? 0),
                                'Valor_Nominal' => $this->limpiarNumero($titulo['vr_nominal'] ?? 0),
                                'Reembolso_G_Desembolso' => $this->limpiarNumero($titulo['reembolso_g_desembolso'] ?? 0),
                                'Base_Negociacion' => $this->limpiarNumero($titulo['base_negociacion'] ?? 0),
                                'Rendimientos_Proyectados' => $this->limpiarNumero($titulo['rend_proyectados'] ?? 0),
                                'Valor_Pagar_Deudor' => $this->limpiarNumero($titulo['valor_pagar_deudor'] ?? 0),
                                'NombreArchivo' => "Confirming_{$opNro}.xlsx"
                            ];
                        }
                    }
                }
                break;

            case 'compraventa':
                $prompt = "Analiza este documento de 'NOTIFICACIÓN DE GARANTÍA MOBILIARIA ENDOSO Y CESIÓN DE DERECHOS ECONÓMICOS'. Extrae la información técnica y los títulos de factura.\n\nJSON REQUERIDO:\n{\n  \"vendedor\": { \"nombre\": \"\", \"nit\": \"\" },\n  \"comprador\": { \"nombre\": \"\", \"nit\": \"\" },\n  \"factor\": { \"nombre\": \"\", \"nit\": \"\" },\n  \"detalle_facturas\": [\n    {\n      \"nro_factura\": \"\",\n      \"valor\": \"\",\n      \"fecha_vencimiento\": \"\"\n    }\n  ],\n  \"pago\": {\n    \"banco\": \"\",\n    \"tipo_cuenta\": \"\",\n    \"cuenta_nro\": \"\"\n  }\n}";

                foreach ($pages as $page) {
                    if (empty($page['markdown'])) continue;
                    $data = $mistralService->getStructuredExtraction($prompt, $page['markdown']);

                    $vendedor = $data['vendedor']['nombre'] ?? "N/A";
                    $vendedorNit = $data['vendedor']['nit'] ?? "N/A";
                    $comprador = $data['comprador']['nombre'] ?? "N/A";
                    $compradorNit = $data['comprador']['nit'] ?? "N/A";
                    $factor = $data['factor']['nombre'] ?? "N/A";
                    $factorNit = $data['factor']['nit'] ?? "N/A";
                    $banco = $data['pago']['banco'] ?? null;
                    $cuenta = $data['pago']['cuenta_nro'] ?? null;

                    if (!empty($data['detalle_facturas']) && is_array($data['detalle_facturas'])) {
                        foreach ($data['detalle_facturas'] as $fac) {
                            $allRows[] = [
                                'Vendedor' => $vendedor,
                                'NIT_Vendedor' => $vendedorNit,
                                'Comprador' => $comprador,
                                'NIT_Comprador' => $compradorNit,
                                'Factor' => $factor,
                                'NIT_Factor' => $factorNit,
                                'Nro_Factura' => $fac['nro_factura'] ?? null,
                                'Valor' => $this->limpiarNumero($fac['valor'] ?? 0),
                                'Fecha_Vencimiento' => $fac['fecha_vencimiento'] ?? null,
                                'Banco' => $banco,
                                'Cuenta_Nro' => $cuenta,
                                'NombreArchivo' => "Compraventa_" . substr($vendedor, 0, 10) . ".xlsx"
                            ];
                        }
                    }
                }
                break;

            case 'pagos_compraventa':
                $prompt = "Analiza este documento de 'PAGOS COMPRA VENTA PAGADOR' de Proseguir. Extrae TODOS los registros de la tabla 'Liquidación' y los campos de la cabecera.\n\nINSTRUCCIONES CRÍTICAS DE CABECERA:\n1. PAGO REF: El valor exacto a la derecha de 'PAGO Nº.' (ej: REF-1-95). NO lo confundas con el NIT.\n2. FECHA RECAUDO: Busca 'Fecha Recaudo:' en el centro del encabezado (ej: 09/10/2025). IGNORA 'Fecha Impresión' y 'Hora Impresión' que están arriba a la derecha.\n3. NIT PAGADOR: El número con guion (ej: 900715610-7) que aparece a la derecha de 'Pagador: BGREEN S A S' bajo la columna 'C.C. / NIT:'.\n4. NIT CLIENTE: El número con guion (ej: 901228343-1) que aparece a la derecha de 'Cliente: LIQUITECH SAS' bajo la columna 'C.C. / NIT:'.\n5. ESTADO: El valor a la derecha de 'ESTADO:' (ej: VALIDADA).\n\nINSTRUCCIONES DE TABLA 'Liquidación':\nExtrae CADA fila de la tabla. No te detengas en la primera. Mapea:\n- op: Columna 'Op#'\n- id_titulo: Columna 'IdTitulo'\n- valor_factura: Columna 'Valor Factura'\n- fec_inicial: Columna 'Fec Inicial'\n- fec_final: Columna 'Fec Final'\n- dias: Columna 'Dias'\n- factor: Columna 'Factor'\n- saldo_capital: Columna 'Saldo Capital'\n- valor_descuento: Columna 'Valor Descuento'\n- capital_pagado: Columna 'Capital Pagado'\n- descuento_mora_causado_np: Columna 'Descuento /Mora Causado No Pagado'\n- rec_descuento_mora_np: Columna 'Rec. Descuento/ Mora Causado NP'\n- total_pagado: Columna 'Total Pagado' (Importante: este es el valor individual por fila)\n- saldo_despues_pago: Columna 'Saldo Después del Pago'\n\nJSON REQUERIDO:\n{\n  \"pago_ref\": \"\",\n  \"pagador\": { \"nombre\": \"\", \"nit\": \"\" },\n  \"cliente\": { \"nombre\": \"\", \"nit\": \"\" },\n  \"concepto\": \"\",\n  \"estado\": \"\",\n  \"fecha_recaudo\": \"\",\n  \"detalle_operaciones\": [\n    {\n      \"op\": \"\", \"id_titulo\": \"\", \"valor_factura\": \"\", \"fec_inicial\": \"\", \"fec_final\": \"\", \"dias\": \"\", \"factor\": \"\", \"saldo_capital\": \"\", \"valor_descuento\": \"\", \"capital_pagado\": \"\", \"descuento_mora_causado_np\": \"\", \"rec_descuento_mora_np\": \"\", \"total_pagado\": \"\", \"saldo_despues_pago\": \"\"\n    }\n  ]\n}";

                foreach ($pages as $page) {
                    if (empty($page['markdown'])) continue;
                    $data = $mistralService->getStructuredExtraction($prompt, $page['markdown']);

                    $pagoRef = $data['pago_ref'] ?? '';
                    $pagador = $data['pagador']['nombre'] ?? '';
                    $pagadorNit = $data['pagador']['nit'] ?? '';
                    $cliente = $data['cliente']['nombre'] ?? '';
                    $clienteNit = $data['cliente']['nit'] ?? '';
                    $concepto = $data['concepto'] ?? '';
                    $estado = $data['estado'] ?? '';
                    $fechaRecaudo = $data['fecha_recaudo'] ?? '';
                    $totales = $data['totales'] ?? [];

                    if (!empty($data['detalle_operaciones']) && is_array($data['detalle_operaciones'])) {
                        foreach ($data['detalle_operaciones'] as $op) {
                            $allRows[] = [
                                'filename' => $upload->filename,
                                'Pago_Ref' => $pagoRef,
                                'Pagador' => $pagador,
                                'NIT_Pagador' => $pagadorNit,
                                'Cliente' => $cliente,
                                'NIT_Cliente' => $clienteNit,
                                'Concepto' => $concepto,
                                'Estado' => $estado,
                                'Fecha_Recaudo' => $fechaRecaudo,
                                'Op' => $op['op'] ?? '',
                                'Id_Titulo' => $op['id_titulo'] ?? '',
                                'Valor_Factura' => $this->limpiarNumero($op['valor_factura'] ?? 0),
                                'Fec_Inicial' => $op['fec_inicial'] ?? '',
                                'Fec_Final' => $op['fec_final'] ?? '',
                                'Dias' => $op['dias'] ?? '',
                                'Factor' => $op['factor'] ?? '',
                                'Saldo_Capital' => $this->limpiarNumero($op['saldo_capital'] ?? 0),
                                'Valor_Descuento' => $this->limpiarNumero($op['valor_descuento'] ?? 0),
                                'Capital_Pagado' => $this->limpiarNumero($op['capital_pagado'] ?? 0),
                                'Descuento_Mora_Causado_No_Pagado' => $this->limpiarNumero($op['descuento_mora_causado_np'] ?? 0),
                                'Rec_Descuento_Mora_Causado_Np' => $this->limpiarNumero($op['rec_descuento_mora_np'] ?? 0),
                                'Total_Pagado' => $this->limpiarNumero($op['total_pagado'] ?? 0),
                                'Saldo_Despues_Pago' => $this->limpiarNumero($op['saldo_despues_pago'] ?? 0),
                                'Total_Recaudo' => $this->limpiarNumero($totales['total_recaudo'] ?? 0),
                                'Valor_Recaudado' => $this->limpiarNumero($totales['valor_recaudado'] ?? 0)
                            ];
                        }
                    }
                }
                break;
        }

        return $allRows;
    }

    /**
     * Cleans number string values and casts to float.
     */
    protected function limpiarNumero($valor): float
    {
        if (is_numeric($valor)) {
            return (float)$valor;
        }
        if (empty($valor) || $valor === '-') {
            return 0.0;
        }
        $limpio = str_replace([',', '$', ' '], '', $valor);
        return (float)$limpio ?: 0.0;
    }
}
