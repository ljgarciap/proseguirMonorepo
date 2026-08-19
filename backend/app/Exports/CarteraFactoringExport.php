<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CarteraFactoringExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
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
        return [
            'Fecha Inicial',
            'Fecha Final',
            'Factura Número',
            'Valor Neto Factura',
            'NIT Cliente',
            'Cliente',
            'NIT Pagador',
            'Pagador',
            'Fecha de Pago',
            'Valor Pagado',
            'Saldo Después del Pago',
        ];
    }

    public function map($row): array
    {
        return [
            $row->fecha_inicial,
            $row->fecha_final,
            $row->factura_numero,
            $row->valor_neto_factura,
            $row->nit_cliente,
            $row->cliente,
            $row->nit_pagador,
            $row->pagador,
            $row->fecha_pago,
            $row->valor_pagado,
            $row->saldo_despues_pago,
        ];
    }
}
