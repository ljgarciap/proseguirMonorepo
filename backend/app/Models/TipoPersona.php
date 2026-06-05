<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoPersona extends Model
{
    protected $table = 'tipo_personas';

    protected $fillable = [
        'nombre',
        'codigo'
    ];
}
