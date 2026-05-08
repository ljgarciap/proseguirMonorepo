<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagoCompraventa extends Model
{
    use HasFactory;

    protected $table = 'pagos_compraventa';

    protected $fillable = [
        'pago_ref',
        'pagador',
        'nit_pagador',
        'cliente',
        'nit_cliente',
        'concepto',
        'estado',
        'fecha_recaudo',
        'op',
        'id_titulo',
        'valor_factura',
        'fec_inicial',
        'fec_final',
        'dias',
        'factor',
        'saldo_capital',
        'valor_descuento',
        'capital_pagado',
        'total_pagado',
        'total_recaudo',
        'valor_recaudado',
        'observaciones',
        'client_upload_id',
    ];
}
