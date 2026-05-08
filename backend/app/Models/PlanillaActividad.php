<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanillaActividad extends Model
{
    protected $fillable = [
        'planilla_finca_id', 'planilla_trabajador_id', 'planilla_labor_id', 'fecha',
        'cantidad', 'precio_unitario', 'subtotal', 'retencion_porcentaje',
        'retencion_valor', 'neto', 'observaciones'
    ];

    public function finca() { return $this->belongsTo(PlanillaFinca::class, 'planilla_finca_id'); }
    public function trabajador() { return $this->belongsTo(PlanillaTrabajador::class, 'planilla_trabajador_id'); }
    public function labor() { return $this->belongsTo(PlanillaLabor::class, 'planilla_labor_id'); }
}
