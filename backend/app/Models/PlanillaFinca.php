<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanillaFinca extends Model
{
    protected $fillable = ['nombre', 'descripcion'];

    public function actividades() { return $this->hasMany(PlanillaActividad::class); }
    public function gastos() { return $this->hasMany(PlanillaGasto::class); }
}
