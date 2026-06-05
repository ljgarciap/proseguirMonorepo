<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCredito extends Model
{
    protected $table = 'tipo_creditos';

    protected $fillable = [
        'nombre',
        'codigo'
    ];
}
