<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    protected $fillable = ['nombre'];

    public function ciudades()
    {
        return $this->hasMany(Ciudad::class);
    }
}
