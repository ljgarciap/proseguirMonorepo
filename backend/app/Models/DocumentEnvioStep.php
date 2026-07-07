<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentEnvioStep extends Model
{
    protected $fillable = [
        'envio_id', 'orden', 'area_id', 'estado',
        'usuario_id', 'fecha_inicio', 'fecha_procesamiento', 'observacion',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_procesamiento' => 'datetime',
    ];

    public function envio()
    {
        return $this->belongsTo(DocumentEnvio::class, 'envio_id');
    }

    public function area()
    {
        return $this->belongsTo(DocumentArea::class, 'area_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
