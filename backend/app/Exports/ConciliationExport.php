<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ConciliationExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithMapping
{
    protected $results;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function collection()
    {
        return collect($this->results);
    }

    public function headings(): array
    {
        return [
            'Estado',
            'Fecha (Susuerte)',
            'Fecha (Banco)',
            'Monto',
            'Descripción (Susuerte)',
            'Descripción (Banco)',
            'Observaciones'
        ];
    }

    public function map($row): array
    {
        return [
            $row['Status'] ?? '',
            $row['Date (Susuerte)'] ?? '',
            $row['Date (Bank)'] ?? '',
            $row['Amount'] ?? '',
            $row['Description (Susuerte)'] ?? '',
            $row['Description (Bank)'] ?? '',
            $row['Observations'] ?? ''
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }
}
