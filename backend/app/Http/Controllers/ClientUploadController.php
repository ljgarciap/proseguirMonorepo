<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ClientUpload;
use Illuminate\Support\Facades\Storage;

class ClientUploadController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = ClientUpload::with(['user', 'validator', 'approver']);

        // Roles Filtering
        if (in_array('cliente', $user->roles) && count($user->roles) === 1) {
            $query->where('user_id', $user->id);
        } else {
            // Operativos/Gerentes/Superadmins: solo deben ver lo que se subió CON ROL de cliente
            $query->where('upload_role', 'cliente');
        }

        // Search Filtering
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhereHas('user', function($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Status Filtering
        if ($request->has('status') && $request->status !== 'todos') {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('perPage', 10);
        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400', 
            'active_role' => 'nullable|string'
        ]);

        $file = $request->file('file');
        $path = $file->store('client_uploads');

        $upload = ClientUpload::create([
            'user_id' => $request->user()->id,
            'upload_role' => $request->active_role ?? 'cliente',
            'filename' => $path,
            'original_name' => $file->getClientOriginalName(),
            'status' => 'pendiente',
        ]);

        // REENVÍO INTERNO A n8n (Sin CORS, sin Firewall)
        try {
            $webhookUrl = config('services.n8n.webhook_url');
            \Illuminate\Support\Facades\Log::info("Intentando enviar a n8n: " . $webhookUrl);
            
            if ($webhookUrl) {
                $response = \Illuminate\Support\Facades\Http::attach(
                    'data', 
                    file_get_contents($file->getRealPath()), 
                    $file->getClientOriginalName()
                )->post($webhookUrl, [
                    'upload_id' => $upload->id,
                    'user_id' => $upload->user_id,
                    'original_name' => $upload->original_name,
                    'categoria' => $request->categoria // <--- ESTO FALTABA
                ]);
                \Illuminate\Support\Facades\Log::info("Respuesta de n8n: " . $response->status());
                \Illuminate\Support\Facades\Log::info("[DEBUG] categoria enviada a n8n: " . ($request->categoria ?? 'NO_ENVIADA') . " | archivo: " . $upload->original_name);
            } else {
                \Illuminate\Support\Facades\Log::warning("No se encontró N8N_INTERNAL_WEBHOOK_URL en la configuración.");
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error enviando a n8n: " . $e->getMessage());
        }

        return response()->json($upload);
    }

    public function validateUpload(Request $request, $id)
    {
        $upload = ClientUpload::findOrFail($id);
        
        $request->validate([
            'observations' => 'nullable|string',
            'action' => 'required|in:validar,rechazar'
        ]);

        if ($request->action === 'validar') {
            $upload->update([
                'status' => 'validado',
                'observations' => $request->observations,
                'validated_by' => $request->user()->id
            ]);
        } else {
            $upload->update([
                'status' => 'rechazado',
                'observations' => $request->observations,
                'validated_by' => $request->user()->id
            ]);
        }

        return response()->json($upload);
    }

    public function approveUpload(Request $request, $id)
    {
        $upload = ClientUpload::findOrFail($id);
        
        $request->validate([
            'action' => 'required|in:aprobar,rechazar',
            'observations' => 'nullable|string'
        ]);

        if ($upload->status !== 'validado') {
            return response()->json(['message' => 'Solo se pueden procesar archivos que ya han sido validados por el operativo.'], 422);
        }

        if ($request->action === 'aprobar') {
            $upload->update([
                'status' => 'aprobado',
                'observations' => $request->observations ?: $upload->observations,
                'approved_by' => $request->user()->id
            ]);
        } else {
            $upload->update([
                'status' => 'rechazado',
                'observations' => $request->observations ?: $upload->observations,
                'approved_by' => $request->user()->id
            ]);
        }

        return response()->json($upload);
    }

    public function pendingCount(Request $request)
    {
        $user = $request->user();
        
        $baseQuery = ClientUpload::where('upload_role', 'cliente');

        if (in_array('cliente', $user->roles) && count($user->roles) === 1) {
            $baseQuery->where('user_id', $user->id);
        }

        $operativoCount = (clone $baseQuery)->where('status', 'pendiente')->count();
        $gerenteCount = (clone $baseQuery)->where('status', 'validado')->count();

        // Documentos Internos Pendientes
        $internalContable = \App\Models\InternalDocument::where('target_role', 'contable')->where('estado', 'pendiente')->count();
        $internalGerente = \App\Models\InternalDocument::where('target_role', 'gerente')->where('estado', 'pendiente')->count();

        // Documentos Internos por Vencer (< 2 horas)
        $expiringContable = 0;
        $expiringGerente = 0;
        $pendingInternals = \App\Models\InternalDocument::with('priority')->where('estado', 'pendiente')->get();
        foreach ($pendingInternals as $doc) {
            if ($doc->priority && $doc->priority->horas_vencimiento) {
                $expiresAt = $doc->created_at->addHours($doc->priority->horas_vencimiento);
                $hoursRemaining = now()->diffInHours($expiresAt, false);
                if ($hoursRemaining <= 2) {
                    if ($doc->target_role === 'contable') $expiringContable++;
                    if ($doc->target_role === 'gerente') $expiringGerente++;
                }
            }
        }

        return response()->json([
            'operativo' => $operativoCount,
            'gerente' => $gerenteCount,
            'contable' => $internalContable,
            'internal_gerente' => $internalGerente,
            'expiring_contable' => $expiringContable,
            'expiring_gerente' => $expiringGerente,
            'total' => $operativoCount + $gerenteCount + $internalContable + $internalGerente
        ]);
    }

    public function download($id)
    {
        $upload = ClientUpload::findOrFail($id);
        
        if (!Storage::exists($upload->filename)) {
            return response()->json(['message' => 'Archivo no encontrado físicamente en el servidor.'], 404);
        }

        return Storage::download($upload->filename, $upload->original_name);
    }

    public function destroy(Request $request, $id)
    {
        $upload = ClientUpload::findOrFail($id);
        $user = $request->user();

        // Si es cliente, solo puede borrar si está pendiente y es suyo
        if (in_array('cliente', $user->roles)) {
            if ($upload->user_id !== $user->id) {
                return response()->json(['message' => 'No tienes permiso para borrar este archivo.'], 403);
            }
            if ($upload->status !== 'pendiente') {
                return response()->json(['message' => 'No puedes borrar un archivo que ya ha sido procesado o validado.'], 422);
            }
        }

        // Borrar archivo físico
        if (Storage::exists($upload->filename)) {
            Storage::delete($upload->filename);
        }

        $upload->delete();

        return response()->json(['message' => 'Archivo eliminado correctamente']);
    }
}
