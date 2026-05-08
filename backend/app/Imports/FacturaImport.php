<?php

namespace App\Imports;

use App\Models\ContableFactura;
use App\Models\ContableImport;
use Maatwebsite\Excel\Concerns\ToModel;

class FacturaImport implements ToModel
{
    private $batchId;
    private $rows = 0;

    public function __construct($batchId)
    {
        $this->batchId = $batchId;
    }

    public function model(array $row)
    {
        $this->rows++;

        // Skip header rows
        $facturaNum = trim($row[0] ?? '');
        $lowercaseFactura = strtolower($facturaNum);

        if (empty($facturaNum) || 
            $lowercaseFactura === 'factura' || 
            str_contains($lowercaseFactura, 'relacion facturas') ||
            str_contains($lowercaseFactura, 'proseguir soluciones') ||
            str_contains($lowercaseFactura, 'fecha') ||
            str_contains($lowercaseFactura, 'documento')
        ) {
            return null;
        }

        // Generate a unique hash including the row number to allow identical trans within same file
        $uniqueHash = md5($facturaNum . $this->rows);

        $factura = ContableFactura::updateOrCreate(
            ['factura' => $facturaNum], // unique identifier by actual invoice number if present
            [
                'import_batch_id' => $this->batchId,
                'pedido' => $row[1] ?? null,
                'cliente' => $row[2] ?? null,
                'nombre' => $row[3] ?? null,
                'email' => $row[4] ?? null,
                'direccion' => $row[5] ?? null,
                'ciudad' => $row[6] ?? null,
                'telefono' => $row[7] ?? null,
                'nit' => $row[8] ?? null,
                'fecha' => $row[9] ? $this->parseDate($row[9]) : null,
                'vencimiento' => $row[10] ? $this->parseDate($row[10]) : null,
                'vlr_bruto' => $this->cleanNumeric($row[11] ?? 0),
                'vlr_dcto' => $this->cleanNumeric($row[12] ?? 0),
                'vlr_iva_5' => $this->cleanNumeric($row[13] ?? 0),
                'vlr_iva_19' => $this->cleanNumeric($row[14] ?? 0),
                'vlr_i_consumo' => $this->cleanNumeric($row[15] ?? 0),
                'total' => $this->cleanNumeric($row[16] ?? 0),
                'observaciones' => $row[17] ?? null,
            ]
        );

        // Update records processed
        ContableImport::where('id', $this->batchId)->increment('records_processed');

        return $factura;
    }

    private function cleanNumeric($value)
    {
        if (is_numeric($value)) return (float) $value;
        if (empty($value)) return 0.0;
        
        // Remove currency symbols, commas and spaces
        $clean = str_replace(['$', ' ', ','], '', $value);
        
        // Handle thousands separator if it's a dot (e.g. 1.234.56 or 1.234)
        // This is tricky because dot is also a decimal separator in some contexts.
        // Assuming Excel formats or Spanish standard where comma is decimal (handled above)
        // or dot is decimal but multiplied. 
        // If we have "1.234.56" -> it should be 1234.56? 
        // Actually, if it has TWO dots, they are definitely thousands separators.
        if (substr_count($clean, '.') > 1) {
            $clean = str_replace('.', '', $clean);
        }
        
        return (float) $clean;
    }

    private function parseDate($value)
    {
        if (!$value) return null;

        if (is_numeric($value)) {
            // Check if it's YYYYMMDD (length 8 and starts with 20 or 19)
            if (strlen($value) == 8 && (str_starts_with($value, '20') || str_starts_with($value, '19'))) {
                return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
            }
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return $value;
            }
        }
        
        // Handle strings with / or -
        $ts = strtotime(str_replace('/', '-', $value));
        return $ts ? date('Y-m-d', $ts) : $value;
    }
}
