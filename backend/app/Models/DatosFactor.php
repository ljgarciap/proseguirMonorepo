<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatosFactor extends Model
{
    protected $table = 'datos_factor';

    protected $fillable = [
        'razon_social',
        'tipo_documento',
        'numero_documento',
        'rep_legal_nombre',
        'rep_legal_tipo_doc',
        'rep_legal_num_doc',
        'rep_legal_email',
    ];
}
