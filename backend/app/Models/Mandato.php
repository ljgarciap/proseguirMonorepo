<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mandato extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mandante_razon_social',
        'mandante_tipo_documento',
        'mandante_numero_documento',
        'mandante_domicilio',
        'mandante_direccion',
        'mandante_telefono',
        'mandante_rep_legal_nombre',
        'mandante_rep_legal_tipo_doc',
        'mandante_rep_legal_num_doc',
        'mandante_rep_legal_email',
        'factor_razon_social',
        'factor_tipo_documento',
        'factor_numero_documento',
        'factor_rep_legal_nombre',
        'factor_rep_legal_tipo_doc',
        'factor_rep_legal_num_doc',
        'factor_rep_legal_email',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
