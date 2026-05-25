<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    /**
     * Display a listing of the resource.
     * Ordered alphabetically ascending by 'nombre'.
     */
    public function index()
    {
        $notificaciones = Notificacion::orderBy('nombre', 'asc')->get();
        return response()->json($notificaciones);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'mensaje' => 'required|string',
            'activo' => 'nullable|boolean'
        ]);

        $notificacion = Notificacion::create([
            'nombre' => $request->nombre,
            'mensaje' => $request->mensaje,
            'activo' => $request->has('activo') ? (bool)$request->activo : true
        ]);

        return response()->json($notificacion, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Notificacion $notificacion)
    {
        return response()->json($notificacion);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Notificacion $notificacion)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'mensaje' => 'required|string',
            'activo' => 'required|boolean'
        ]);

        $notificacion->update([
            'nombre' => $request->nombre,
            'mensaje' => $request->mensaje,
            'activo' => (bool)$request->activo
        ]);

        return response()->json($notificacion);
    }

    /**
     * Remove the specified resource from storage.
     * Block delete if there are associated recipients.
     */
    public function destroy(Notificacion $notificacion)
    {
        // Regla de Negocio: No se permite eliminar si hay destinatarios asociados en la tabla intermedia
        if ($notificacion->destinatarios()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la notificación porque tiene destinatarios asociados.'
            ], 422);
        }

        $notificacion->delete();
        return response()->json(null, 204);
    }
}
