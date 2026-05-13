<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InternalDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class InternalDocumentController extends Controller
{
    /**
     * Listar documentos según el rol del usuario autenticado
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $roles = $user->roles ?? [];
        
        $query = InternalDocument::with(['sender', 'category', 'priority']);
        $isAdmin = in_array('superadmin', $roles);

        if (!$isAdmin) {
            $query->where(function($q) use ($user, $roles) {
                if (in_array('operativo', $roles)) $q->orWhere('sender_id', $user->id);
                if (in_array('contable', $roles)) $q->orWhere('target_role', 'contable');
                if (in_array('gerente', $roles)) $q->orWhere('target_role', 'gerente');
            });
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    /**
     * Subir un nuevo documento interno
     */
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string',
            'archivo' => 'required|file|max:10240', // Max 10MB
            'target_role' => 'required|in:contable,gerente',
            'categoria_id' => 'required|exists:accounting_categories,id',
            'prioridad_id' => 'required|exists:accounting_priorities,id',
        ]);

        $path = $request->file('archivo')->store('internal_docs', 'public');

        $doc = InternalDocument::create([
            'sender_id' => Auth::id(),
            'target_role' => $request->target_role,
            'titulo' => $request->titulo,
            'archivo_path' => $path,
            'categoria_id' => $request->categoria_id,
            'prioridad_id' => $request->prioridad_id,
            'mensaje' => $request->mensaje,
            'estado' => 'pendiente'
        ]);

        return response()->json($doc, 201);
    }

    /**
     * Actualizar estado del documento (visto, procesado, rechazado)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:visto,procesado,rechazado'
        ]);

        $doc = InternalDocument::findOrFail($id);
        $doc->update(['estado' => $request->estado]);

        return response()->json($doc);
    }

    /**
     * Eliminar documento y su archivo físico
     */
    public function destroy($id)
    {
        $doc = InternalDocument::findOrFail($id);
        
        // Validación de permisos
        $isSender = $doc->sender_id === Auth::id();
        $isAdmin = in_array('superadmin', Auth::user()->roles);

        if (!$isSender && !$isAdmin) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Solo se puede borrar si aún está pendiente (a menos que sea admin)
        if ($isSender && !$isAdmin && $doc->estado !== 'pendiente') {
            return response()->json(['message' => 'No puede eliminar un documento que ya está en proceso.'], 403);
        }

        Storage::disk('public')->delete($doc->archivo_path);
        $doc->delete();

        return response()->json(['message' => 'Documento eliminado']);
    }
}
