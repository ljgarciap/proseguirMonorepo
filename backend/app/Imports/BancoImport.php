<?php

namespace App\Imports;

use App\Models\ContableBanco;
use App\Models\ContableImport;
use Maatwebsite\Excel\Concerns\ToModel;

class BancoImport implements ToModel
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
        $fechaRaw = trim($row[0] ?? '');
        $lowercaseFecha = strtolower($fechaRaw);
        
        if (empty($fechaRaw) || 
            $lowercaseFecha === 'fecha' || 
            str_contains($lowercaseFecha, 'co') || 
            str_contains($lowercaseFecha, 'documento')
        ) {
            return null;
        }
        
        $fecha = $this->parseDate($fechaRaw);
        $descripcion = trim($row[1] ?? '');
        $valorRaw = $row[4] ?? 0;
        $valor = $this->cleanNumeric($valorRaw);

        // Skip rows that look like Auxiliar headers/junk if mistakenly uploaded
        if (strlen($descripcion) < 3 && is_numeric($descripcion)) {
             return null;
        }

        // Generate a unique hash including the row number to allow identical trans within same file
        $uniqueHash = md5($fecha . $descripcion . $valor . $this->rows);

        $banco = ContableBanco::updateOrCreate(
            ['unique_hash' => $uniqueHash],
            [
                'import_batch_id' => $this->batchId,
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'sucursal' => $row[2] ?? null,
                'dcto' => $this->cleanNumeric($row[3] ?? 0),
                'valor' => $valor,
            ]
        );

        ContableImport::where('id', $this->batchId)->increment('records_processed');

        return $banco;
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
            // Check if it's YYYYMMDD
            if (strlen($value) == 8 && (str_starts_with($value, '20') || str_starts_with($value, '19'))) {
                return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
            }
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return $value;
            }
        }
        
        // Handle D/MM
        if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $value, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            return date('Y') . "-$month-$day";
        }

        $ts = strtotime(str_replace('/', '-', $value));
        return $ts ? date('Y-m-d', $ts) : $value;
    }
}
