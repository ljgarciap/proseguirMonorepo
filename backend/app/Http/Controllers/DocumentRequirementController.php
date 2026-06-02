<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequirement;
use Illuminate\Http\Request;

class DocumentRequirementController extends Controller
{
    public function index()
    {
        return response()->json(DocumentRequirement::orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'activo' => 'nullable|boolean'
        ]);

        $requirement = DocumentRequirement::create($data);
        return response()->json($requirement, 201);
    }

    public function update(Request $request, $id)
    {
        $requirement = DocumentRequirement::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'activo' => 'nullable|boolean'
        ]);

        $requirement->update($data);
        return response()->json($requirement);
    }

    public function destroy($id)
    {
        $requirement = DocumentRequirement::findOrFail($id);
        $requirement->delete();
        return response()->json(['message' => 'Requisito de documento eliminado correctamente.']);
    }
}
