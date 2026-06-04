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
}
