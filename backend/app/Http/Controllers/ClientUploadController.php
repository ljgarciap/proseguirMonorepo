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
        if (in_array('cliente', $user->roles)) {
            $query->where('user_id', $user->id);
        } else {
            // Operativos/Gerentes/Superadmins: solo deben ver cargas de CLIENTES
            $query->whereHas('user', function($q) {
                $q->where('roles', 'like', '%"cliente"%');
            });
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
            'file' => 'required|file|max:102400', // Aumentado a 100MB para coincidir con Nginx
        ]);

        $file = $request->file('file');
        $path = $file->store('client_uploads');

        $upload = ClientUpload::create([
            'user_id' => $request->user()->id,
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
        
        $baseQuery = ClientUpload::whereHas('user', function($q) {
            $q->where('roles', 'like', '%"cliente"%');
        });

        if (in_array('cliente', $user->roles)) {
            $baseQuery->where('user_id', $user->id);
        }

        $operativoCount = (clone $baseQuery)->where('status', 'pendiente')->count();
        $gerenteCount = (clone $baseQuery)->where('status', 'validado')->count();

        return response()->json([
            'operativo' => $operativoCount,
            'gerente' => $gerenteCount,
            'total' => $operativoCount + $gerenteCount
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
