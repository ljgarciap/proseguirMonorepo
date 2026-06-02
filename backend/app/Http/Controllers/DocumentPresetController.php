<?php

namespace App\Http\Controllers;

use App\Models\DocumentPreset;
use Illuminate\Http\Request;

class DocumentPresetController extends Controller
{
    public function index()
    {
        return response()->json(DocumentPreset::with('requirements')->orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'requirements' => 'required|array',
            'requirements.*' => 'exists:document_requirements,id'
        ]);

        $preset = DocumentPreset::create([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null
        ]);

        $preset->requirements()->attach($data['requirements']);

        return response()->json($preset->load('requirements'), 201);
    }

    public function update(Request $request, $id)
    {
        $preset = DocumentPreset::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'requirements' => 'sometimes|required|array',
            'requirements.*' => 'exists:document_requirements,id'
        ]);

        if (isset($data['nombre'])) {
            $preset->nombre = $data['nombre'];
        }
        if (array_key_exists('descripcion', $data)) {
            $preset->descripcion = $data['descripcion'];
        }
        $preset->save();

        if (isset($data['requirements'])) {
            $preset->requirements()->sync($data['requirements']);
        }

        return response()->json($preset->load('requirements'));
    }

    public function destroy($id)
    {
        $preset = DocumentPreset::findOrFail($id);
        $preset->requirements()->detach();
        $preset->delete();
        return response()->json(['message' => 'Preset de documentos eliminado correctamente.']);
    }
}
