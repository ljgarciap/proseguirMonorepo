<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * SCRUM-246 — Lectura del log de actividad de usuarios. Sin update/delete:
 * la tabla es append-only por convención de aplicación (ver
 * ActivityLog/ActivityLogService). Ruta protegida con
 * checkrole:superadmin (ver routes/api.php).
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $accion = $request->query('accion');
        $usuarioId = $request->query('usuario_id');
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');
        $sortBy = $request->query('sortBy', 'created_at');
        $sortDir = $request->query('sortDir', 'desc');
        $perPage = $request->query('perPage', 15);

        $query = ActivityLog::query();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('descripcion', 'LIKE', "%{$search}%")
                    ->orWhere('nombre_usuario', 'LIKE', "%{$search}%")
                    ->orWhere('accion', 'LIKE', "%{$search}%")
                    ->orWhere('direccion_ip', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($accion)) {
            $query->where('accion', $accion);
        }

        if (!empty($usuarioId)) {
            $query->where('usuario_id', $usuarioId);
        }

        if (!empty($desde)) {
            $query->whereDate('created_at', '>=', $desde);
        }

        if (!empty($hasta)) {
            $query->whereDate('created_at', '<=', $hasta);
        }

        return response()->json($query->orderBy($sortBy, $sortDir)->paginate($perPage));
    }

    /**
     * Lista de valores distintos de `accion` ya registrados, para poblar
     * el filtro de la UI sin hardcodear el catálogo en el frontend — a
     * medida que se conecten más eventos (créditos, actas, documentos) el
     * filtro los suma solo.
     */
    public function acciones()
    {
        return response()->json(
            ActivityLog::query()->distinct()->orderBy('accion')->pluck('accion')
        );
    }
}
