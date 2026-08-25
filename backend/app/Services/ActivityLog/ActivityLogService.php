<?php

namespace App\Services\ActivityLog;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * SCRUM-246 — Punto único para registrar actividad de usuarios. Un
 * controller que quiera sumar un evento nuevo (transición de crédito,
 * aprobación de acta, subida/descarga de documento, etc.) solo llama
 * ActivityLogService::registrar() en el momento relevante — no necesita
 * saber nada de la tabla ni de cómo se arma la fila.
 *
 * A propósito NO es middleware/listener genérico sobre todos los
 * requests: eso loggearía cada GET de la app (ruido, nada "útil y
 * relevante" como pidió Luis) — cada llamado acá es una decisión explícita
 * de que ESE evento vale la pena auditar.
 */
class ActivityLogService
{
    public function registrar(
        string $accion,
        string $descripcion,
        ?User $usuario = null,
        ?Model $entidad = null,
        array $metadata = [],
        ?Request $request = null,
    ): ActivityLog {
        $request ??= request();

        return ActivityLog::create([
            'usuario_id' => $usuario?->id,
            'nombre_usuario' => $usuario?->name,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'entidad_type' => $entidad ? get_class($entidad) : null,
            'entidad_id' => $entidad?->getKey(),
            'direccion_ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata ?: null,
        ]);
    }
}
