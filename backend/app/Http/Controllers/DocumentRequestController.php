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

    /**
     * SCRUM-176: cada SolicitudCredito tiene su propio DocumentRequest
     * (SCRUM-152), así que un cliente puede tener varios "pendiente"
     * simultáneos — uno por solicitud, sin que las anteriores se cierren
     * automáticamente. Antes se tomaba el primero sin ORDER BY (equivalente
     * al más viejo/estancado): si el cliente tenía una solicitud anterior
     * sin cerrar, sus cargas nuevas quedaban atadas a ese DocumentRequest
     * viejo y el crédito realmente activo nunca veía su Etapa 1 satisfecha
     * — bloqueado para siempre en 'validacion_documental_constructor' /
     * 'revision_documental' sin ningún error visible ni para el cliente
     * (ve "Carga Exitosa") ni para el Coordinador (solo ve "Faltan
     * documentos obligatorios" repetido). Esto también explica por qué la
     * trazabilidad de esos créditos nunca avanzaba más allá de la carga
     * documental del cliente: el resto del BPMN jamás se alcanzaba.
     * Se prioriza el DocumentRequest cuyo CreditoOrdinario está realmente
     * esperando al cliente ahora mismo; si ninguno calza (p.ej. todavía en
     * revisión inicial), se usa el más reciente en vez del más viejo.
     */
    public function activeRequest(Request $request)
    {
        $user = $request->user();

        $pendientes = DocumentRequest::where('cliente_id', $user->id)
            ->where('estado', 'pendiente')
            ->with(['items.requirement', 'items.upload', 'solicitudCredito.creditoOrdinario'])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($pendientes->isEmpty()) {
            return response()->json(null);
        }

        $estadosEsperandoCliente = ['completar_solicitud', 'completar_solicitud_constructor'];
        $active = $pendientes->first(function (DocumentRequest $dr) use ($estadosEsperandoCliente) {
            return in_array($dr->solicitudCredito?->creditoOrdinario?->estado, $estadosEsperandoCliente, true);
        }) ?? $pendientes->first();

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
