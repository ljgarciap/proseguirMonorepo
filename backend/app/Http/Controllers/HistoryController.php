<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\HistoryExport;

class HistoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/history/{categoria}",
     *     summary="Listar registros históricos por categoría",
     *     tags={"Historia"},
     *     @OA\Parameter(
     *         name="categoria",
     *         in="path",
     *         required=true,
     *         description="Categoría (cartera, op, pagos, opf)",
     *         @OA\Schema(type="string", enum={"cartera", "op", "pagos", "opf"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Búsqueda global en todas las columnas",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Lista paginada")
     * )
     */
    public function index(Request $request, $categoria)
    {
        $search = $request->query('search');
        $filter = $request->query('filter');
        $sortBy = $request->query('sortBy', 'id');
        $sortDir = $request->query('sortDir', 'desc');
        $perPage = $request->query('perPage', 15);

        $modelClass = null;
        switch ($categoria) {
            case 'cartera':
                $modelClass = \App\Models\OperacionCartera::class;
                break;
            case 'op':
                $modelClass = \App\Models\OperacionFactoring::class;
                break;
            case 'pagos':
                $modelClass = \App\Models\PagoFactoring::class;
                break;
            case 'opf':
                $modelClass = \App\Models\OperacionConfirming::class;
                break;
            case 'compraventa':
                $modelClass = \App\Models\Compraventa::class;
                break;
            case 'pagos_compraventa':
                $modelClass = \App\Models\PagoCompraventa::class;
                break;
            default:
                return response()->json(['message' => 'Categoría no válida'], 400);
        }

        $query = $modelClass::query();

        // Specific Filters (from Dashboard clicks)
        if ($categoria === 'cartera') {
            if ($filter === 'mora') {
                $query->where('valor_mora', '>', 0);
            } elseif ($filter === 'vencido') {
                $query->where('dias_vencido', '>', 0);
            }
        }

        if (!empty($search)) {
            $table = (new $modelClass)->getTable();
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
     * @OA\Patch(
     *     path="/api/history/{categoria}/{id}",
     *     summary="Actualizar campos editables de un registro",
     *     tags={"Historia"},
     *     @OA\Parameter(
     *         name="categoria",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="observaciones", type="string"),
     *             @OA\Property(property="sector_economico", type="string"),
     *             @OA\Property(property="ciudad", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Registro actualizado")
     * )
     */
    public function updateRecord(Request $request, $categoria, $id)
    {
        $modelClass = null;
        switch ($categoria) {
            case 'cartera': $modelClass = \App\Models\OperacionCartera::class; break;
            case 'op': $modelClass = \App\Models\OperacionFactoring::class; break;
            case 'pagos': $modelClass = \App\Models\PagoFactoring::class; break;
            case 'opf': $modelClass = \App\Models\OperacionConfirming::class; break;
            case 'compraventa': $modelClass = \App\Models\Compraventa::class; break;
            case 'pagos_compraventa': $modelClass = \App\Models\PagoCompraventa::class; break;
            default: return response()->json(['message' => 'Categoría no válida'], 400);
        }

        $record = $modelClass::findOrFail($id);
        $data = $request->only(['observaciones', 'sector_economico', 'ciudad']);
        
        // Logical Learning: If the user manually fixes a sector, we "learn" it.
        if (isset($data['sector_economico']) && $categoria === 'cartera' && !empty($record->actividad_economica)) {
            \App\Models\SectorMapping::updateOrCreate(
                ['actividad_economica' => $record->actividad_economica],
                ['sector' => $data['sector_economico']]
            );
        }

        $record->update($data);

        return response()->json(['message' => 'Registro actualizado', 'data' => $record]);
    }

    public function deleteRecord($categoria, $id)
    {
        $modelClass = null;
        switch ($categoria) {
            case 'cartera': $modelClass = \App\Models\OperacionCartera::class; break;
            case 'op': $modelClass = \App\Models\OperacionFactoring::class; break;
            case 'pagos': $modelClass = \App\Models\PagoFactoring::class; break;
            case 'opf': $modelClass = \App\Models\OperacionConfirming::class; break;
            case 'compraventa': $modelClass = \App\Models\Compraventa::class; break;
            case 'pagos_compraventa': $modelClass = \App\Models\PagoCompraventa::class; break;
            default: return response()->json(['message' => 'Categoría no válida'], 400);
        }

        $record = $modelClass::findOrFail($id);
        $record->delete();

        return response()->json(['message' => 'Registro eliminado correctamente']);
    }

    /**
     * @OA\Get(
     *     path="/api/history/{categoria}/export",
     *     summary="Exportar registros a Excel",
     *     tags={"Historia"},
     *     @OA\Parameter(
     *         name="categoria",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Archivo Excel")
     * )
     */
    public function export(Request $request, $categoria)
    {
        $search = $request->query('search');
        $modelClass = null;
        switch ($categoria) {
            case 'cartera': $modelClass = \App\Models\OperacionCartera::class; break;
            case 'op': $modelClass = \App\Models\OperacionFactoring::class; break;
            case 'pagos': $modelClass = \App\Models\PagoFactoring::class; break;
            case 'opf': $modelClass = \App\Models\OperacionConfirming::class; break;
            case 'compraventa': $modelClass = \App\Models\Compraventa::class; break;
            case 'pagos_compraventa': $modelClass = \App\Models\PagoCompraventa::class; break;
            default: abort(400);
        }
        $query = $modelClass::query();
        if (!empty($search)) {
            $table = (new $modelClass)->getTable();
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
            $query->where(function($q) use ($columns, $search) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$search}%");
                }
            });
        }
        $records = $query->get();
        $filename = "export_{$categoria}_" . date('Ymd_His') . ".xlsx";
        return Excel::download(new HistoryExport($records), $filename);
    }

    public function deleteByUpload($uploadId)
    {
        $tables = [
            \App\Models\OperacionCartera::class,
            \App\Models\OperacionFactoring::class,
            \App\Models\PagoFactoring::class,
            \App\Models\OperacionConfirming::class,
            \App\Models\Compraventa::class,
            \App\Models\PagoCompraventa::class
        ];

        $deletedTotal = 0;
        foreach ($tables as $modelClass) {
            $deletedTotal += $modelClass::where('client_upload_id', $uploadId)->delete();
        }

        return response()->json([
            'message' => 'Limpieza masiva completada',
            'deleted_records' => $deletedTotal
        ]);
    }
}
