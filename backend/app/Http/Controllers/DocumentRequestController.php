<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\DocumentPreset;
use App\Models\User;
use Illuminate\Http\Request;

class DocumentRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = DocumentRequest::with(['cliente', 'creador', 'items.requirement', 'items.upload']);

        if ($request->has('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->has('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        return response()->json($query->orderBy('created_at', 'desc')->paginate(10));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required|exists:users,id',
            'preset_id' => 'nullable|exists:document_presets,id',
            'requirements' => 'nullable|array',
            'requirements.*' => 'exists:document_requirements,id'
        ]);

        $cliente = User::findOrFail($request->cliente_id);

        // Check if there is already a pending request for this client
        $existing = DocumentRequest::where('cliente_id', $cliente->id)
            ->where('estado', 'pendiente')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'El cliente ya tiene una solicitud de documentos activa en estado pendiente.'
            ], 422);
        }

        // Determine requirements to request
        $requirementIds = [];
        if ($request->filled('preset_id')) {
            $preset = DocumentPreset::findOrFail($request->preset_id);
            $requirementIds = $preset->requirements()->pluck('document_requirements.id')->toArray();
        } else if ($request->filled('requirements')) {
            $requirementIds = $request->requirements;
        }

        if (empty($requirementIds)) {
            return response()->json([
                'message' => 'Debe seleccionar al menos un requisito de documento o plantilla.'
            ], 422);
        }

        $documentRequest = DocumentRequest::create([
            'cliente_id' => $cliente->id,
            'creado_por' => $request->user()->id,
            'estado' => 'pendiente'
        ]);

        foreach ($requirementIds as $reqId) {
            DocumentRequestItem::create([
                'document_request_id' => $documentRequest->id,
                'document_requirement_id' => $reqId,
                'estado' => 'pendiente'
            ]);
        }

        return response()->json($documentRequest->load('items.requirement'), 201);
    }

    public function activeRequest(Request $request)
    {
        $user = $request->user();
        
        $active = DocumentRequest::where('cliente_id', $user->id)
            ->where('estado', 'pendiente')
            ->with(['items.requirement', 'items.upload'])
            ->first();

        if (!$active) {
            return response()->json(null);
        }

        return response()->json($active);
    }

    public function getClients()
    {
        // Helper to list all users who are clients
        $clients = User::whereJsonContains('roles', 'cliente')->orderBy('name')->get();
        return response()->json($clients);
    }

    public function destroy($id)
    {
        $docRequest = DocumentRequest::findOrFail($id);
        $docRequest->delete(); // Cascades items delete via DB foreign key constraint
        return response()->json(['message' => 'Solicitud de documentos eliminada correctamente.']);
    }
}
