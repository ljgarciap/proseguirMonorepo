<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HistoryExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $records;

    public function __construct($records)
    {
        $this->records = $records;
    }

    public function collection()
    {
        return $this->records;
    }

    public function headings(): array
    {
        if ($this->records->isEmpty()) {
            return [];
        }
        $attributes = array_keys($this->records->first()->getAttributes());
        $filtered = array_filter($attributes, function($key) {
            return !in_array($key, ['created_at', 'updated_at', 'deleted_at']);
        });

        return array_map(function($key) {
            return strtoupper(str_replace('_', ' ', $key));
        }, array_values($filtered));
    }

    public function map($record): array
    {
        $attributes = $record->getAttributes();
        $filtered = array_filter($attributes, function($value, $key) {
            return !in_array($key, ['created_at', 'updated_at', 'deleted_at']);
        }, ARRAY_FILTER_USE_BOTH);

        return array_values($filtered);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
