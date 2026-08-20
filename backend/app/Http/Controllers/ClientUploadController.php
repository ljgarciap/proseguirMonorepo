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
        if (in_array('cliente', $user->roles) && !array_intersect($user->roles, ['superadmin', 'gerente', 'operativo', 'contable', 'coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'tesoreria'])) {
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
            'active_role' => 'nullable|string',
            'document_request_item_id' => 'nullable|exists:document_request_items,id',
            'category' => 'nullable|string',
            'categoria' => 'nullable|string'
        ]);

        $file = $request->file('file');
        $path = $file->store('client_uploads');

        $category = $request->category ?? $request->categoria;

        $upload = ClientUpload::create([
            'user_id' => $request->user()->id,
            'upload_role' => $request->active_role ?? 'cliente',
            'category' => $category,
            'filename' => $path,
            'original_name' => $file->getClientOriginalName(),
            'status' => 'pendiente',
            'ocr_status' => 'procesando',
            'ocr_message' => 'Enviado a cola de procesamiento'
        ]);

        if ($category) {
            \Illuminate\Support\Facades\Cache::put("pending_upload_{$category}", $upload->id, 300);
        }

        if ($request->filled('document_request_item_id')) {
            $item = \App\Models\DocumentRequestItem::findOrFail($request->document_request_item_id);
            $item->update([
                'client_upload_id' => $upload->id,
                'estado' => 'subido',
                'observaciones' => null
            ]);

            // SCRUM-193/205 (2026-08-17): a diferencia del pipeline de OCR
            // (validateUpload()/approveUpload() → syncRequestItem()), los
            // documentos dirigidos por document_request_item_id (SCRUM-146,
            // usado acá para el preset de garantías) no pasan por ahí — acá
            // es donde el cliente efectivamente "termina de subir" el ítem.
            if ($item->request) {
                $this->habilitarFormalizacionGarantiasSiAplica($item->request);
            }
        }

        // Dispatch OCR processing job
        try {
            if ($category) {
                \App\Jobs\ProcessUploadJob::dispatch($upload->id, $category);
                \Illuminate\Support\Facades\Log::info("Despachado ProcessUploadJob local para upload ID: {$upload->id}, categoria: {$category}");
            } else {
                $upload->update([
                    'ocr_status' => 'exitoso',
                    'ocr_message' => 'No requiere procesamiento OCR'
                ]);
                \Illuminate\Support\Facades\Log::warning("No se especificó categoría para el upload ID: {$upload->id}, omitiendo procesamiento OCR.");
            }
        } catch (\Exception $e) {
            $upload->update([
                'ocr_status' => 'fallido',
                'ocr_message' => 'Error al despachar trabajo: ' . $e->getMessage()
            ]);
            \Illuminate\Support\Facades\Log::error("Error despachando ProcessUploadJob: " . $e->getMessage());
        }

        return response()->json($upload);
    }

    public function recentOcr(Request $request)
    {
        $user = $request->user();
        $query = ClientUpload::query();

        // Si es cliente, solo ve los suyos
        if (in_array('cliente', $user->roles) && !array_intersect($user->roles, ['superadmin', 'gerente', 'operativo', 'contable', 'coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'tesoreria'])) {
            $query->where('user_id', $user->id);
        }

        // Obtener los últimos 5 archivos subidos en las últimas 24 horas o que estén procesando
        $uploads = $query->where(function($q) {
            $q->where('created_at', '>=', now()->subHours(24))
              ->orWhere('ocr_status', 'procesando');
        })
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

        return response()->json($uploads);
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

        $this->syncRequestItem($upload);

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

        $this->syncRequestItem($upload);

        return response()->json($upload);
    }

    public function pendingCount(Request $request)
    {
        $user = $request->user();
        
        $baseQuery = ClientUpload::where('upload_role', 'cliente');

        if (in_array('cliente', $user->roles) && !array_intersect($user->roles, ['superadmin', 'gerente', 'operativo', 'contable', 'coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'tesoreria'])) {
            $baseQuery->where('user_id', $user->id);
        }

        $operativoCount = (clone $baseQuery)->where('status', 'pendiente')->count();
        $gerenteCount = (clone $baseQuery)->where('status', 'validado')->count();

        // Documentos Internos Pendientes (bandeja vieja, target_role fijo).
        // Se excluyen los que ya fueron migrados a la Bandeja Interna nueva (SCRUM-94,
        // ver MigrateLegacyInternalDocuments): esa migración no borra las filas
        // originales, así que sin este filtro un documento migrado se cuenta dos
        // veces (una en el modelo viejo, otra en el nuevo).
        $migratedBatchKeys = \App\Models\DocumentEnvio::whereNotNull('legacy_batch_key')->pluck('legacy_batch_key')->all();

        $pendingInternals = \App\Models\InternalDocument::with('priority')
            ->where('estado', 'pendiente')
            ->get()
            ->reject(fn ($doc) => in_array($doc->batch_id ?? ('single_' . $doc->id), $migratedBatchKeys));

        $internalContable = $pendingInternals->where('target_role', 'contable')->count();
        $internalGerente = $pendingInternals->where('target_role', 'gerente')->count();
        $internalOperativo = $pendingInternals->where('target_role', 'operativo')->count();

        // Documentos Internos por Vencer (< 2 horas) - bandeja vieja
        $expiringContable = 0;
        $expiringGerente = 0;
        $expiringOperativo = 0;
        foreach ($pendingInternals as $doc) {
            if ($doc->priority && $doc->priority->horas_vencimiento) {
                $expiresAt = $doc->created_at->addHours($doc->priority->horas_vencimiento);
                $hoursRemaining = now()->diffInHours($expiresAt, false);
                if ($hoursRemaining <= 2) {
                    if ($doc->target_role === 'contable') $expiringContable++;
                    if ($doc->target_role === 'gerente') $expiringGerente++;
                    if ($doc->target_role === 'operativo') $expiringOperativo++;
                }
            }
        }

        // Envíos de la Bandeja Interna nueva (SCRUM-94): pasos pendientes cuyo turno es el actual
        $pasosActuales = \App\Models\DocumentEnvioStep::with(['area', 'envio.priority'])
            ->where('estado', 'pendiente')
            ->get()
            ->filter(fn($step) => $step->envio && $step->orden === $step->envio->current_step_order);

        foreach ($pasosActuales as $step) {
            switch ($step->area?->codigo) {
                case 'contable': $internalContable++; break;
                case 'gerente': $internalGerente++; break;
                case 'operativo': $internalOperativo++; break;
            }

            $envio = $step->envio;
            if ($envio->priority && $envio->priority->horas_vencimiento) {
                $expiresAt = $envio->created_at->copy()->addHours($envio->priority->horas_vencimiento);
                $hoursRemaining = now()->diffInHours($expiresAt, false);
                if ($hoursRemaining <= 2) {
                    switch ($step->area?->codigo) {
                        case 'contable': $expiringContable++; break;
                        case 'gerente': $expiringGerente++; break;
                        case 'operativo': $expiringOperativo++; break;
                    }
                }
            }
        }

        $pendingMandates = \App\Models\Mandato::where('status', 'pendiente')->count();

        return response()->json([
            'operativo' => $operativoCount,
            'gerente' => $gerenteCount,
            'contable' => $internalContable,
            'internal_gerente' => $internalGerente,
            'internal_operativo' => $internalOperativo,
            'expiring_contable' => $expiringContable,
            'expiring_gerente' => $expiringGerente,
            'expiring_operativo' => $expiringOperativo,
            'pending_mandates' => $pendingMandates,
            'total' => $operativoCount + $gerenteCount + $internalContable + $internalGerente + $internalOperativo + $pendingMandates
        ]);
    }

    public function download(Request $request, $id)
    {
        $upload = ClientUpload::findOrFail($id);
        $user = $request->user();

        // Si es cliente, validar que sea su propio archivo
        if (in_array('cliente', $user->roles) && !array_intersect($user->roles, ['superadmin', 'gerente', 'operativo', 'contable', 'coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'tesoreria'])) {
            if ($upload->user_id !== $user->id) {
                return response()->json(['message' => 'No tienes permiso para ver este archivo.'], 403);
            }
        }
        
        if (Storage::exists($upload->filename)) {
            return Storage::download($upload->filename, $upload->original_name);
        }

        // SCRUM-146: los archivos sincronizados desde Crédito Ordinario
        // Etapa 1 se guardan en el disco 'public' (para el link directo de
        // esa vista), no en el disco por defecto que usa el resto de cargas
        // de cliente.
        if (Storage::disk('public')->exists($upload->filename)) {
            return Storage::disk('public')->download($upload->filename, $upload->original_name);
        }

        return response()->json(['message' => 'Archivo no encontrado físicamente en el servidor.'], 404);
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

        // Restablecer item de solicitud asociado si existe
        if ($upload->requestItem) {
            $upload->requestItem->update([
                'client_upload_id' => null,
                'estado' => 'pendiente',
                'observaciones' => null
            ]);
        }

        // Borrar archivo físico (ver nota de disco en download())
        if (Storage::exists($upload->filename)) {
            Storage::delete($upload->filename);
        } elseif (Storage::disk('public')->exists($upload->filename)) {
            Storage::disk('public')->delete($upload->filename);
        }

        $upload->delete();

        return response()->json(['message' => 'Archivo eliminado correctamente']);
    }

    private function syncRequestItem(ClientUpload $upload)
    {
        $item = \App\Models\DocumentRequestItem::where('client_upload_id', $upload->id)->first();
        if ($item) {
            $nuevoEstado = 'pendiente';
            if ($upload->status === 'pendiente') $nuevoEstado = 'subido';
            elseif ($upload->status === 'validado') $nuevoEstado = 'validado';
            elseif ($upload->status === 'aprobado') $nuevoEstado = 'aprobado';
            elseif ($upload->status === 'rechazado') $nuevoEstado = 'rechazado';

            $item->update([
                'estado' => $nuevoEstado,
                'observaciones' => $upload->observations
            ]);

            $request = $item->request;
            if ($request && $request->estado !== 'cancelado') {
                $pendingItems = $request->items()->where('estado', '!=', 'aprobado')->count();
                if ($pendingItems === 0) {
                    $request->update(['estado' => 'completado']);
                    $this->habilitarInformeTecnicoSiAplica($request);
                } else {
                    $request->update(['estado' => 'pendiente']);
                }

                $this->habilitarFormalizacionGarantiasSiAplica($request);
            }
        }
    }

    /**
     * SCRUM-193/205 (2026-08-17): cuando el cliente terminó de subir TODOS
     * los ítems del preset de garantías (no hace falta que estén aprobados
     * todavía, eso lo decide el rol Operativo en Formalización de
     * Garantías — SCRUM-237, antes Coordinador Comercial) el crédito pasa
     * de 'aprobada_garantias' a 'pendiente_formalizacion_garantias' — a
     * diferencia del chequeo de arriba (que exige 'aprobado' para dar la
     * request por completada), acá alcanza con que ningún ítem siga
     * 'pendiente' de carga. Guard defensivo por estado, mismo criterio que
     * GestionCreditoController::retomarComiteSiAplica(), para no
     * interferir con otros flujos que comparten syncRequestItem() (SCRUM-146
     * onboarding inicial, SCRUM-191 reenvío de pendiente_comite).
     */
    private function habilitarFormalizacionGarantiasSiAplica(\App\Models\DocumentRequest $request): void
    {
        if (!$request->solicitud_credito_id) {
            return;
        }

        $credito = \App\Models\CreditoOrdinario::where('solicitud_credito_id', $request->solicitud_credito_id)->first();
        if (!$credito || $credito->estado !== 'aprobada_garantias') {
            return;
        }

        $itemsSinSubir = $request->items()->where('estado', 'pendiente')->count();
        if ($itemsSinSubir > 0) {
            return;
        }

        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => 'Sistema',
            'rol' => 'sistema',
            'estado_anterior' => $credito->estado,
            'estado_nuevo' => 'pendiente_formalizacion_garantias',
            'comentario' => 'El cliente terminó de diligenciar las garantías solicitadas. Bandeja de Formalización de Garantías habilitada para el rol Operativo.',
        ];

        $credito->estado = 'pendiente_formalizacion_garantias';
        $credito->solicitud_gestionada = false;
        $credito->fecha_gestion = null;
        $credito->historial_estados = $historial;
        $credito->save();
    }

    /**
     * SCRUM-120: cuando una DocumentRequest queda completa (todos los items
     * aprobados) y pertenece a una SolicitudCredito de tipo Constructor, la
     * bandeja de Informe Técnico se habilita transicionando el
     * CreditoOrdinario asociado a 'informe_tecnico_ingeniero'.
     */
    private function habilitarInformeTecnicoSiAplica(\App\Models\DocumentRequest $request): void
    {
        if (!$request->solicitud_credito_id) {
            return;
        }

        $solicitud = \App\Models\SolicitudCredito::with('tipoCredito')->find($request->solicitud_credito_id);
        if (!$solicitud || !$solicitud->tipoCredito || strtoupper($solicitud->tipoCredito->codigo) !== 'CONSTRUCTOR') {
            return;
        }

        $credito = \App\Models\CreditoOrdinario::where('solicitud_credito_id', $solicitud->id)->first();
        if (!$credito || $credito->estado !== 'validacion_documental_constructor') {
            return;
        }

        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => 'Sistema',
            'rol' => 'sistema',
            'estado_anterior' => $credito->estado,
            'estado_nuevo' => 'informe_tecnico_ingeniero',
            'comentario' => 'Documentación requerida validada. Bandeja de Informe Técnico habilitada para el Ingeniero.',
        ];

        $credito->estado = 'informe_tecnico_ingeniero';
        $credito->historial_estados = $historial;
        $credito->save();
    }
}
