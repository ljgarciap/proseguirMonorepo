<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanillaGasto extends Model
{
    protected $fillable = [
        'planilla_finca_id', 'fecha', 'concepto', 'beneficiario',
        'valor', 'tipo', 'observaciones'
    ];

    public function finca() { return $this->belongsTo(PlanillaFinca::class, 'planilla_finca_id'); }
}
