<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanillaTrabajador extends Model
{
    protected $fillable = ['nombre', 'identificacion', 'telefono', 'retencion_pactada'];

    public function actividades() { return $this->hasMany(PlanillaActividad::class); }
}
