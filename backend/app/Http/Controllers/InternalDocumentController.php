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
                $q->where('sender_id', $user->id);
                
                if (in_array('operativo', $roles)) {
                    $q->orWhere('target_role', 'operativo');
                    // Operativo can also see pending documents sent by coordinador_comercial targeting gerente
                    $q->orWhere(function($sub) {
                        $sub->where('target_role', 'gerente')
                            ->where('estado', 'pendiente')
                            ->whereHas('sender', function($qu) {
                                $qu->whereJsonContains('roles', 'coordinador_comercial');
                            });
                    });
                }
                if (in_array('contable', $roles)) {
                    $q->orWhere('target_role', 'contable');
                }
                if (in_array('gerente', $roles)) {
                    // Gerente sees target gerente. But if sent by coordinador_comercial, only show if not pending (needs visto_bueno first)
                    $q->orWhere(function($sub) {
                        $sub->where('target_role', 'gerente')
                            ->where(function($stateSub) {
                                $stateSub->where('estado', '!=', 'pendiente')
                                    ->orWhere(function($orSub) {
                                        $orSub->whereDoesntHave('sender', function($qu) {
                                            $qu->whereJsonContains('roles', 'coordinador_comercial');
                                        });
                                    });
                            });
                    });
                }
            });
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    /**
     * Subir uno o varios documentos internos
     */
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string',
            'archivo' => 'nullable|file|max:10240',
            'archivos' => 'nullable|array',
            'archivos.*' => 'file|max:10240',
            'target_role' => 'required|in:contable,gerente,operativo',
            'categoria_id' => 'required|exists:accounting_categories,id',
            'prioridad_id' => 'required|exists:accounting_priorities,id',
        ]);

        if (!$request->hasFile('archivo') && !$request->hasFile('archivos')) {
            return response()->json(['message' => 'Debe seleccionar al menos un archivo.'], 422);
        }

        $docs = [];
        $batchId = uniqid('batch_');

        if ($request->hasFile('archivos')) {
            $files = $request->file('archivos');
            $isMultiple = count($files) > 1;
            foreach ($files as $file) {
                $path = $file->store('internal_docs', 'public');
                $originalName = $file->getClientOriginalName();
                $docTitle = $isMultiple ? ($request->titulo . ' - ' . $originalName) : $request->titulo;

                $docs[] = InternalDocument::create([
                    'sender_id' => Auth::id(),
                    'batch_id' => $batchId,
                    'target_role' => $request->target_role,
                    'titulo' => $docTitle,
                    'archivo_path' => $path,
                    'original_name' => $originalName,
                    'categoria_id' => $request->categoria_id,
                    'prioridad_id' => $request->prioridad_id,
                    'mensaje' => $request->mensaje,
                    'estado' => 'pendiente'
                ]);
            }
        } else {
            $file = $request->file('archivo');
            $path = $file->store('internal_docs', 'public');
            $originalName = $file->getClientOriginalName();

            $docs[] = InternalDocument::create([
                'sender_id' => Auth::id(),
                'batch_id' => null,
                'target_role' => $request->target_role,
                'titulo' => $request->titulo,
                'archivo_path' => $path,
                'original_name' => $originalName,
                'categoria_id' => $request->categoria_id,
                'prioridad_id' => $request->prioridad_id,
                'mensaje' => $request->mensaje,
                'estado' => 'pendiente'
            ]);
        }

        return response()->json(count($docs) === 1 ? $docs[0] : $docs, 201);
    }

    /**
     * Actualizar estado de varios documentos en bloque
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:internal_documents,id',
            'estado' => 'required|in:visto,visto_bueno,procesado,rechazado',
            'motivo_rechazo' => 'nullable|string'
        ]);

        $docs = InternalDocument::whereIn('id', $request->ids)->get();

        foreach ($docs as $doc) {
            $updateData = ['estado' => $request->estado, 'last_action_at' => now()];
            if ($request->estado === 'rechazado') {
                $updateData['motivo_rechazo'] = $request->motivo_rechazo;
            } else {
                $updateData['motivo_rechazo'] = null;
            }
            $doc->update($updateData);
        }

        return response()->json(['message' => 'Documentos actualizados con éxito', 'docs' => $docs]);
    }

    /**
     * Actualizar estado del documento (visto, visto_bueno, procesado, rechazado)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:visto,visto_bueno,procesado,rechazado',
            'motivo_rechazo' => 'nullable|string'
        ]);

        $doc = InternalDocument::findOrFail($id);
        
        $updateData = ['estado' => $request->estado, 'last_action_at' => now()];
        if ($request->estado === 'rechazado') {
            $updateData['motivo_rechazo'] = $request->motivo_rechazo;
        } else {
            $updateData['motivo_rechazo'] = null;
        }

        $doc->update($updateData);

        return response()->json($doc);
    }

    /**
     * Eliminar varios documentos y sus archivos físicos en bloque
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:internal_documents,id',
        ]);

        $docs = InternalDocument::whereIn('id', $request->ids)->get();
        $isAdmin = in_array('superadmin', Auth::user()->roles);

        foreach ($docs as $doc) {
            $isSender = $doc->sender_id === Auth::id();
            if (!$isSender && !$isAdmin) {
                return response()->json(['message' => 'No autorizado para eliminar algunos de los documentos.'], 403);
            }
            if ($isSender && !$isAdmin && $doc->estado !== 'pendiente') {
                return response()->json(['message' => 'No puede eliminar documentos que ya están en proceso.'], 403);
            }
        }

        foreach ($docs as $doc) {
            Storage::disk('public')->delete($doc->archivo_path);
            $doc->delete();
        }

        return response()->json(['message' => 'Documentos eliminados con éxito']);
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
