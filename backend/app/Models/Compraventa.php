<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Compraventa extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendedor',
        'nit_vendedor',
        'comprador',
        'nit_comprador',
        'factor',
        'nit_factor',
        'nro_factura',
        'valor',
        'fecha_vencimiento',
        'banco',
        'cuenta_nro',
        'client_upload_id',
        'observaciones',
    ];
}
