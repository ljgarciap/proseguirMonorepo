<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SystemLogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/logs",
     *     summary="Listar logs de auditoría del sistema",
     *     tags={"Logs"},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Búsqueda en logs",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Lista paginada de logs")
     * )
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy', 'id');
        $sortDir = $request->query('sortDir', 'desc');
        $perPage = $request->query('perPage', 15);

        $query = \App\Models\SystemLog::query();

        if (!empty($search)) {
            $table = (new \App\Models\SystemLog)->getTable();
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
            
            $query->where(function($q) use ($columns, $search) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$search}%");
                }
            });
        }

        return response()->json($query->orderBy($sortBy, $sortDir)->paginate($perPage));
    }

    /**
     * @OA\Post(
     *     path="/api/logs/{id}/retry",
     *     summary="Reintentar operación fallida desde webhook",
     *     tags={"Logs"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del Log",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Reintento exitoso"),
     *     @OA\Response(response=400, description="Log no válido para reintento")
     * )
     */
    public function retry($id)
    {
        $log = \App\Models\SystemLog::findOrFail($id);

        if (!$log->payload) {
            return response()->json(['message' => 'Este log no tiene un payload guardado para reintentar.'], 400);
        }

        $data = json_decode($log->payload, true);
        if (!$data) {
            return response()->json(['message' => 'El payload no tiene un formato JSON válido.'], 400);
        }

        try {
            $webhookController = new \App\Http\Controllers\N8nWebhookController();
            $webhookController->processData($data, $log->categoria, $log->filename);

            \App\Models\SystemLog::create([
                'categoria' => $log->categoria,
                'filename' => $log->filename,
                'action' => 'Reintento Exitoso',
                'message' => "Reintento manual del log #{$log->id} procesado con éxito.",
                'records_processed' => count($data),
                'payload' => $log->payload
            ]);

            return response()->json(['message' => 'Datos reprocesados correctamente.']);
            
        } catch (\Throwable $e) {
            \App\Models\SystemLog::create([
                'categoria' => $log->categoria,
                'filename' => $log->filename,
                'action' => 'Error en Reintento',
                'message' => "Error reintentando log #{$log->id}: " . $e->getMessage(),
                'records_processed' => 0,
                'payload' => $log->payload,
                'error_details' => $e->getTraceAsString()
            ]);
            
            return response()->json(['message' => 'Error en el reintento', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $log = \App\Models\SystemLog::findOrFail($id);
        $log->delete();

        return response()->json(['message' => 'Log de auditoría eliminado']);
    }
}
