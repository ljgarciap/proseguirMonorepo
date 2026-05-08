<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanillaLabor extends Model
{
    protected $fillable = ['nombre', 'unidad', 'precio_sugerido', 'retencion_sugerida'];

    public function actividades() { return $this->hasMany(PlanillaActividad::class); }
}
