<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentArea extends Model
{
    protected $fillable = ['nombre', 'codigo', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function steps()
    {
        return $this->hasMany(DocumentEnvioStep::class, 'area_id');
    }
}
