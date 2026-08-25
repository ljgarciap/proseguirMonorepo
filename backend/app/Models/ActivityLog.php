<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SCRUM-246 — Fila de auditoría de actividad de usuarios. Solo se crea vía
 * ActivityLogService::registrar() — ningún controller debe llamar
 * ActivityLog::create() directo, para que el punto de conexión de un
 * evento nuevo quede siempre en el servicio (mismo criterio que
 * FirmaElectronicaService en SCRUM-245).
 */
class ActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'activity_logs';

    protected $fillable = [
        'usuario_id',
        'nombre_usuario',
        'accion',
        'descripcion',
        'entidad_type',
        'entidad_id',
        'direccion_ip',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
