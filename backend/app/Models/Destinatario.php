<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Destinatario extends Model
{
    protected $table = 'destinatarios';

    protected $fillable = [
        'nombre',
        'email',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    /**
     * Relación de muchos a muchos con el modelo Notificacion.
     */
    public function notificaciones()
    {
        return $this->belongsToMany(Notificacion::class, 're_notificacion_destinatario', 'destinatario_id', 'notificacion_id')
                    ->withPivot('activo')
                    ->withTimestamps();
    }
}
