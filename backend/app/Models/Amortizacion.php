<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Amortizacion extends Model
{
    protected $table = 'amortizaciones';

    protected $fillable = [
        'nombre',
        'codigo'
    ];
}
