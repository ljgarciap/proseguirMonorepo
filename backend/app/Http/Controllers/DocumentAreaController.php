<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentArea;

class DocumentAreaController extends Controller
{
    /**
     * Listar áreas del catálogo (usado por el selector de ruta de aprobación).
     */
    public function index(Request $request)
    {
        $query = DocumentArea::query();

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        return response()->json($query->orderBy('nombre')->get());
    }

    /**
     * Crear una nueva área (superadmin).
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string',
            'codigo' => 'required|string|unique:document_areas,codigo',
        ]);

        $area = DocumentArea::create([
            'nombre' => $request->nombre,
            'codigo' => $request->codigo,
            'activo' => true,
        ]);

        return response()->json($area, 201);
    }

    /**
     * Editar un área existente (superadmin).
     */
    public function update(Request $request, $id)
    {
        $area = DocumentArea::findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|required|string',
            'codigo' => 'sometimes|required|string|unique:document_areas,codigo,' . $area->id,
            'activo' => 'sometimes|boolean',
        ]);

        $area->update($request->only(['nombre', 'codigo', 'activo']));

        return response()->json($area);
    }

    /**
     * Eliminar un área (superadmin). Si ya tiene pasos de ruta asociados,
     * se desactiva en lugar de eliminarse para no perder trazabilidad histórica.
     */
    public function destroy($id)
    {
        $area = DocumentArea::findOrFail($id);

        if ($area->steps()->exists()) {
            $area->update(['activo' => false]);
            return response()->json([
                'message' => 'El área tiene pasos de aprobación asociados; se desactivó en lugar de eliminarse.',
                'area' => $area,
            ]);
        }

        $area->delete();

        return response()->json(['message' => 'Área eliminada con éxito.']);
    }
}
