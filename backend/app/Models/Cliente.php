<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $fillable = [
        'nombre',
        'identificacion',
        'ciudad',
        'sector_economico',
        'actividad_economica',
        'is_verified',
        'verification_method'
    ];
}
