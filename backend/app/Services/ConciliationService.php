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
        
        $data = [];
        preg_match_all('/(\d{4}\/\d{2}\/\d{2})\s+(.*?)\s+([-\d,.]+)/', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $amount = $this->parseAmount($match[3]);
            if ($amount <= 0) continue;

            $data[] = [
                'date' => str_replace('/', '-', $match[1]),
                'amount' => $amount,
                'description' => trim($match[2]),
                'source' => 'Bank'
            ];
        }

        \Log::info("Bank entries found: " . count($data));
        return $data;
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
