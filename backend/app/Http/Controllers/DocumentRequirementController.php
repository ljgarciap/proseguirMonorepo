<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'activo' => 'nullable|boolean',
            'tiene_plantilla' => 'nullable|boolean',
            'plantilla' => 'nullable|file|max:10240'
        ]);

        if (isset($data['tiene_plantilla'])) {
            $data['tiene_plantilla'] = filter_var($data['tiene_plantilla'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('plantilla')) {
            $file = $request->file('plantilla');
            $path = $file->store('document_templates', 'public');
            $data['plantilla_path'] = $path;
            $data['plantilla_nombre'] = $file->getClientOriginalName();
            $data['tiene_plantilla'] = true;
        }

        $requirement = DocumentRequirement::create($data);
        return response()->json($requirement, 201);
    }

    public function update(Request $request, $id)
    {
        $requirement = DocumentRequirement::findOrFail($id);

        // Standard PUT/PATCH with files in PHP sometimes requires POST with _method spoofing.
        // We handle fields safely here.
        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'activo' => 'nullable|boolean',
            'tiene_plantilla' => 'nullable|boolean',
            'plantilla' => 'nullable|file|max:10240'
        ]);

        if (isset($data['tiene_plantilla'])) {
            $data['tiene_plantilla'] = filter_var($data['tiene_plantilla'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('plantilla')) {
            if ($requirement->plantilla_path && Storage::disk('public')->exists($requirement->plantilla_path)) {
                Storage::disk('public')->delete($requirement->plantilla_path);
            }
            $file = $request->file('plantilla');
            $path = $file->store('document_templates', 'public');
            $data['plantilla_path'] = $path;
            $data['plantilla_nombre'] = $file->getClientOriginalName();
            $data['tiene_plantilla'] = true;
        } else {
            if (isset($data['tiene_plantilla']) && !$data['tiene_plantilla']) {
                if ($requirement->plantilla_path && Storage::disk('public')->exists($requirement->plantilla_path)) {
                    Storage::disk('public')->delete($requirement->plantilla_path);
                }
                $data['plantilla_path'] = null;
                $data['plantilla_nombre'] = null;
            }
        }

        $requirement->update($data);
        return response()->json($requirement);
    }

    public function downloadTemplate($id)
    {
        $requirement = DocumentRequirement::findOrFail($id);

        if (!$requirement->plantilla_path || !Storage::disk('public')->exists($requirement->plantilla_path)) {
            return response()->json(['message' => 'El formato o plantilla no existe.'], 404);
        }

        return Storage::disk('public')->download($requirement->plantilla_path, $requirement->plantilla_nombre);
    }

    public function destroy($id)
    {
        $requirement = DocumentRequirement::findOrFail($id);
        if ($requirement->plantilla_path && Storage::disk('public')->exists($requirement->plantilla_path)) {
            Storage::disk('public')->delete($requirement->plantilla_path);
        }
        $requirement->delete();
        return response()->json(['message' => 'Requisito de documento eliminado correctamente.']);
    }
}
