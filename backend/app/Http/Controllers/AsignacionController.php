<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use App\Models\Destinatario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsignacionController extends Controller
{
    /**
     * Display a listing of notifications with their assigned recipients count.
     */
    public function index()
    {
        $asignaciones = Notificacion::withCount('destinatarios')
            ->orderBy('nombre', 'asc')
            ->get();
            
        return response()->json($asignaciones);
    }

    /**
     * Display the specified notification's current assigned recipients
     * along with all active recipients for the UI selector.
     */
    public function show($id)
    {
        $notificacion = Notificacion::findOrFail($id);
        
        $asignados = $notificacion->destinatarios()->orderBy('nombre', 'asc')->get();
        
        // Cargar todos los destinatarios activos para el selector
        $activos = Destinatario::where('activo', true)
            ->orderBy('nombre', 'asc')
            ->get();
            
        return response()->json([
            'notificacion' => $notificacion,
            'asignados' => $asignados,
            'activos' => $activos
        ]);
    }

    /**
     * Store and sync a batch assignment of recipients to a notification.
     */
    public function store(Request $request)
    {
        $request->validate([
            'notificacion_id' => 'required|integer|exists:notificaciones,id',
            'destinatario_ids' => 'required|array',
            'destinatario_ids.*' => 'integer|exists:destinatarios,id'
        ]);

        $notificacion = Notificacion::findOrFail($request->notificacion_id);

        // Regla de Negocio: Solo notificaciones activas pueden asociarse
        if (!$notificacion->activo) {
            return response()->json([
                'message' => 'No se pueden asociar destinatarios a una notificación inactiva.'
            ], 422);
        }

        // Regla de Negocio: Solo destinatarios activos pueden asociarse
        $inactivosCount = Destinatario::whereIn('id', $request->destinatario_ids)
            ->where('activo', false)
            ->count();

        if ($inactivosCount > 0) {
            return response()->json([
                'message' => 'Solo destinatarios activos pueden asociarse a notificaciones.'
            ], 422);
        }

        // Sincronización atómica usando una transacción
        DB::transaction(function () use ($notificacion, $request) {
            $notificacion->destinatarios()->sync($request->destinatario_ids);
        });

        return response()->json([
            'message' => 'Asignaciones guardadas correctamente.',
            'cantidad' => count($request->destinatario_ids)
        ]);
    }

    /**
     * Remove all assignments from a specific notification.
     */
    public function destroy($id)
    {
        $notificacion = Notificacion::findOrFail($id);
        
        DB::transaction(function () use ($notificacion) {
            $notificacion->destinatarios()->detach();
        });

        return response()->json(null, 204);
    }
}
