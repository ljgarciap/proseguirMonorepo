<?php

namespace App\Imports;

use App\Models\ContableGasto;
use App\Models\ContableImport;
use Maatwebsite\Excel\Concerns\ToModel;

class GastoImport implements ToModel
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

        $fechaRaw = trim($row[0] ?? '');
        $lowercaseFecha = strtolower($fechaRaw);

        if (empty($fechaRaw) || 
            $lowercaseFecha === 'fecha' || 
            str_contains($lowercaseFecha, 'proveedor') ||
            str_contains($lowercaseFecha, 'documento')
        ) {
            return null;
        }

        $fecha = $this->parseDate($fechaRaw);
        $documento = trim($row[1] ?? '');
        $concepto = trim($row[4] ?? '');
        $valor = $this->cleanNumeric($row[5] ?? 0);

        // Generate a unique hash including the row number to allow identical trans within same file
        $uniqueHash = md5($fecha . $documento . $concepto . $valor . $this->rows);

        $gasto = ContableGasto::updateOrCreate(
            ['unique_hash' => $uniqueHash],
            [
                'import_batch_id' => $this->batchId,
                'fecha' => $fecha,
                'documento' => $documento,
                'proveedor' => $row[2] ?? null,
                'nit' => $row[3] ?? null,
                'concepto' => $concepto,
                'valor' => $valor,
                'observaciones' => $row[6] ?? null,
            ]
        );

        ContableImport::where('id', $this->batchId)->increment('records_processed');

        return $gasto;
    }

    private function cleanNumeric($value)
    {
        if (is_numeric($value)) return (float) $value;
        if (empty($value)) return 0.0;
        $clean = str_replace(['$', ' ', ','], '', $value);
        if (substr_count($clean, '.') > 1) {
            $clean = str_replace('.', '', $clean);
        }
        return (float) $clean;
    }

    private function parseDate($value)
    {
        if (!$value) return null;

        if (is_numeric($value)) {
            if (strlen($value) == 8 && (str_starts_with($value, '20') || str_starts_with($value, '19'))) {
                return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
            }
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return $value;
            }
        }
        
        $ts = strtotime(str_replace('/', '-', $value));
        return $ts ? date('Y-m-d', $ts) : $value;
    }
}
