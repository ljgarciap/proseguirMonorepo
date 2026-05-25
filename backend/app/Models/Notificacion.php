<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'nombre',
        'mensaje',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    /**
     * Relación de muchos a muchos con el modelo Destinatario.
     */
    public function destinatarios()
    {
        return $this->belongsToMany(Destinatario::class, 're_notificacion_destinatario', 'notificacion_id', 'destinatario_id')
                    ->withPivot('activo')
                    ->withTimestamps();
    }
}
