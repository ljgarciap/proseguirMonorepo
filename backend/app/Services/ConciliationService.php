<?php

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ConciliationExport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ConciliationService
{
    public function conciliate($xlsxPath, $pdfPath)
    {
        // 1. Read XLSX
        $susuerteData = $this->readSusuerteXlsx($xlsxPath);

        // 2. Read PDF via Node Helper
        $bankData = $this->readBankPdf($pdfPath);

        // 3. Perform Reconciliation
        return $this->performMatching($susuerteData, $bankData);
    }

    private function readSusuerteXlsx($path)
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        
        $data = [];
        foreach ($sheetData as $rowIndex => $row) {
            if ($rowIndex < 3) continue; // Skip headers
            
            $fechaRaw = $row['A'];
            if (!$fechaRaw) continue;

            $amountRaw = $row['D'];
            $amount = $this->parseAmount($amountRaw);
            if ($amount <= 0) continue;

            try {
                if (is_numeric($fechaRaw)) {
                    $date = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($fechaRaw));
                } else {
                    // Try to parse as d/m/Y first (common in Colombia)
                    $date = null;
                    $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d'];
                    foreach ($formats as $fmt) {
                        try {
                            $date = Carbon::createFromFormat($fmt, trim($fechaRaw));
                            break;
                        } catch (\Exception $e) {}
                    }
                    if (!$date) $date = Carbon::parse($fechaRaw);
                }
            } catch (\Exception $e) {
                continue;
            }

            $data[] = [
                'date' => $date->format('Y-m-d'),
                'amount' => $amount,
                'description' => $row['F'] ?? '',
                'source' => 'Susuerte',
                'original_row' => $rowIndex
            ];
        }
        return $data;
    }

    private function readBankPdf($pdfPath)
    {
        $nodeHelperPath = base_path('extract_pdf.cjs');
        $jsonOutput = shell_exec("node " . escapeshellarg($nodeHelperPath) . " " . escapeshellarg($pdfPath));
        $extracted = json_decode($jsonOutput, true);
        $text = $extracted['text'] ?? '';

        return $this->parseBankText($text);
    }

    private function parseBankText($text)
    {
        $data = [];
        // $active acumula las columnas de la transacción en curso. Normalmente
        // se cierra con la misma línea que la abrió, pero el extractor de PDF a
        // veces parte un pago en dos renglones (el VALOR queda en la línea
        // siguiente, sin fecha) — ver SCRUM-125. Esas líneas huérfanas se
        // fusionan aquí en vez de descartarse.
        $active = null;

        foreach (explode("\n", $text) as $line) {
            if (trim($line) === '') continue;

            $columns = explode('|', $line);
            $startsNewRecord = preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $columns[0]);

            if ($startsNewRecord) {
                // Cierra la transacción anterior (si quedó pendiente de una
                // continuación que nunca llegó) antes de abrir esta.
                if ($active !== null) {
                    $this->pushBankRowIfValid($active, $data);
                }
                $active = $columns;
            } else {
                if ($active === null) continue; // huérfana sin transacción previa a la cual fusionarse
                $active = array_merge($active, $columns);
            }

            // Si con lo acumulado hasta ahora ya se arma un registro válido,
            // se cierra de inmediato. Esto evita que una línea posterior no
            // relacionada (pie de página, totales) se fusione por error con
            // un pago que ya estaba completo.
            if ($this->pushBankRowIfValid($active, $data)) {
                $active = null;
            }
        }

        if ($active !== null) {
            $this->pushBankRowIfValid($active, $data);
        }

        \Log::info("Bank entries found: " . count($data));
        return $data;
    }

    /**
     * Valida un conjunto de columnas (fecha + ... + VALOR) y, si es válido,
     * lo agrega a $data. Devuelve true si se agregó.
     */
    private function pushBankRowIfValid(array $columns, array &$data): bool
    {
        $dateRaw = $columns[0];
        if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $dateRaw)) return false;

        // La última columna siempre es VALOR, sin importar cuántas columnas
        // de REFERENCIA/DOCUMENTO haya en el medio.
        $valorRaw = trim($columns[count($columns) - 1]);

        // Un VALOR real siempre trae separador de miles y 2 decimales
        // (ej: "26,700.00"). Si no cumple el formato, lo que llegó ahí
        // es un número de REFERENCIA/DOCUMENTO pegado sin separador
        // (o texto, ej. nombre de remitente Nequi) — no un monto todavía.
        if (!preg_match('/^-?[\d.,]*\d[.,]\d{2}$/', $valorRaw)) return false;

        $amount = $this->parseAmount($valorRaw);
        if ($amount <= 0) return false;

        $description = trim(implode(' ', array_slice($columns, 1, count($columns) - 2)));

        $data[] = [
            'date' => str_replace('/', '-', $dateRaw),
            'amount' => $amount,
            'description' => $description,
            'source' => 'Bank'
        ];

        return true;
    }

    private function performMatching($susuerteData, $bankData)
    {
        $unmatchedSusuerte = $susuerteData;
        $unmatchedBank = $bankData;
        $results = [];

        foreach ($unmatchedSusuerte as $sKey => $sItem) {
            foreach ($unmatchedBank as $bKey => $bItem) {
                if (abs($sItem['amount'] - $bItem['amount']) < 0.01) {
                    $sDate = Carbon::parse($sItem['date']);
                    $bDate = Carbon::parse($bItem['date']);
                    
                    if ($sDate->diffInDays($bDate) <= 1) {
                        $results[] = [
                            'Status' => 'CONCILIADO',
                            'Date (Susuerte)' => $sItem['date'],
                            'Date (Bank)' => $bItem['date'],
                            'Amount' => $sItem['amount'],
                            'Description (Susuerte)' => $sItem['description'],
                            'Description (Bank)' => $bItem['description']
                        ];
                        unset($unmatchedBank[$bKey]);
                        unset($unmatchedSusuerte[$sKey]);
                        continue 2;
                    }
                }
            }
        }

        foreach ($unmatchedSusuerte as $sItem) {
            $results[] = [
                'Status' => 'SOLO EN SUSUERTE',
                'Date (Susuerte)' => $sItem['date'],
                'Date (Bank)' => '-',
                'Amount' => $sItem['amount'],
                'Description (Susuerte)' => $sItem['description'],
                'Description (Bank)' => '-'
            ];
        }

        foreach ($unmatchedBank as $bItem) {
            $results[] = [
                'Status' => 'SOLO EN BANCO',
                'Date (Susuerte)' => '-',
                'Date (Bank)' => $bItem['date'],
                'Amount' => $bItem['amount'],
                'Description (Susuerte)' => '-',
                'Description (Bank)' => $bItem['description']
            ];
        }

        return $results;
    }

    private function parseAmount($val)
    {
        if (is_numeric($val)) return (float)$val;
        
        // Remove everything except digits, comma and dot
        $val = preg_replace('/[^\d,.]/', '', $val);
        
        $dotPos = strrpos($val, '.');
        $commaPos = strrpos($val, ',');
        
        $separator = (($dotPos !== false && $commaPos !== false) && $dotPos > $commaPos) || ($dotPos !== false && $commaPos === false) ? '.' : ',';
        
        if ($separator === ',') {
            // It's 1.000,00 style
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } else {
            // It's 1,000.00 style
            $val = str_replace(',', '', $val);
        }
        
        return (float)$val;
    }
}
