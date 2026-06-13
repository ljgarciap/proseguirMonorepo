<?php

namespace App\Exports;

use App\Models\Mandato;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MandatoExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $mandato;

    public function __construct(Mandato $mandato)
    {
        $this->mandato = $mandato;
    }

    public function collection()
    {
        return collect([$this->mandato]);
    }

    public function headings(): array
    {
        return [
            'Nombre / Razón Social Mandante',
            'Tipo documento de identidad Mandante',
            'Número de documento de identidad Mandante',
            'Domicilio Mandante',
            'Nombre Representante Legal Mandante',
            'Tipo documento de identidad Representante legal Mandante',
            'Número documento de identidad Representante Legal Mandante',
            'Telefono Mandante',
            'Dirección Mandante',
            'E-mail Representante Legal Mandante',
            'Nombre / Razón Social Factor',
            'Tipo documento de identidad Factor',
            'Número de documento de identidad Factor',
            'Nombre Representante legal Factor',
            'Tipo documento de identidad Representante Legal Factor',
            'Número documento de identidad Representante Factor',
            'E-mail Representante Legal Factor',
        ];
    }

    public function map($mandato): array
    {
        return [
            $mandato->mandante_razon_social,
            $mandato->mandante_tipo_documento,
            $mandato->mandante_numero_documento,
            $mandato->mandante_domicilio,
            $mandato->mandante_rep_legal_nombre,
            $mandato->mandante_rep_legal_tipo_doc,
            $mandato->mandante_rep_legal_num_doc,
            $mandato->mandante_telefono,
            $mandato->mandante_direccion,
            $mandato->mandante_rep_legal_email,
            $mandato->factor_razon_social,
            $mandato->factor_tipo_documento,
            $mandato->factor_numero_documento,
            $mandato->factor_rep_legal_nombre,
            $mandato->factor_rep_legal_tipo_doc,
            $mandato->factor_rep_legal_num_doc,
            $mandato->factor_rep_legal_email,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
