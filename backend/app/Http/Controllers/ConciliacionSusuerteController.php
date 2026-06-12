<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConciliacionSusuerte;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class ConciliacionSusuerteController extends Controller
{
    /**
     * Get paginated history for the authenticated user.
     */
    public function history(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);
        $query = ConciliacionSusuerte::where('user_id', Auth::id())
            ->orderBy('conciliated_at', 'desc');
        $data = $query->paginate($perPage);
        return response()->json($data);
    }

    /**
     * Start a new conciliation (clear current view).
     */
    public function newConciliation(): JsonResponse
    {
        // This endpoint simply acknowledges the request; the frontend will reset the view.
        return response()->json(['message' => 'Nueva conciliación iniciada']);
    }

    /**
     * Update details/observations of a conciliation record.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'details' => 'required|array'
        ]);

        $conciliacion = ConciliacionSusuerte::findOrFail($id);
        $conciliacion->update([
            'details' => $request->input('details')
        ]);

        // Re-generate Excel file with updated details
        $fileName = 'conciliacion_' . $conciliacion->conciliated_at->format('Ymd_His') . '.xlsx';
        \Maatwebsite\Excel\Facades\Excel::store(new \App\Exports\ConciliationExport($conciliacion->details), $fileName, 'public');

        return response()->json([
            'message' => 'Observaciones guardadas con éxito',
            'download_url' => '/storage/' . $fileName
        ]);
    }
}
