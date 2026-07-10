<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ciudad extends Model
{
    protected $table = 'ciudades';

    protected $fillable = ['nombre', 'departamento_id'];

    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }
}
