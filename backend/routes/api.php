<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/webhook/n8n/{categoria}', [\App\Http\Controllers\N8nWebhookController::class, 'handle'])
    ->middleware(\App\Http\Middleware\VerifyN8nToken::class);

Route::post('/login', [AuthController::class, 'login']);


Route::get('/test-zia', function (Request $request) {
    return response()->json([
        'mensaje' => 'Si esto sale 200, ya lo tenemos',
        'user' => $request->user('api')
    ]);
})->middleware('auth:api');

Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:api');

Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'stats'])->middleware(['auth:api', 'checkrole:gerente,operativo,superadmin']);

        // Update history routes with auth and role checks
        Route::get('/history/{categoria}', [\App\Http\Controllers\HistoryController::class, 'index'])
            ->middleware(['auth:api', 'checkrole:gerente,operativo,superadmin']);
        
        Route::patch('/history/{categoria}/{id}', [\App\Http\Controllers\HistoryController::class, 'updateRecord'])
            ->middleware(['auth:api', 'checkrole:gerente,operativo,superadmin']);
        
        Route::get('/history/{categoria}/export', [\App\Http\Controllers\HistoryController::class, 'export']);
        
        // Sectores
        Route::get('/sectores', \App\Http\Controllers\SectorController::class)
            ->middleware(['auth:api', 'checkrole:superadmin']);
        
        // Logs
        Route::get('/logs', [\App\Http\Controllers\SystemLogController::class, 'index'])
            ->middleware(['auth:api', 'checkrole:gerente,operativo,superadmin']);
        
        Route::delete('/logs/{id}', [\App\Http\Controllers\SystemLogController::class, 'destroy'])
            ->middleware(['auth:api', 'checkrole:gerente,operativo,superadmin']);

        Route::post('/logs/{id}/retry', [\App\Http\Controllers\SystemLogController::class, 'retry'])
            ->middleware(['auth:api', 'checkrole:gerente,operativo,superadmin']);
            
        Route::delete('/history/{categoria}/{id}', [\App\Http\Controllers\HistoryController::class, 'deleteRecord'])
            ->middleware(['auth:api', 'checkrole:gerente,operativo,superadmin']);

        Route::delete('/history/by-upload/{uploadId}', [\App\Http\Controllers\HistoryController::class, 'deleteByUpload'])
            ->middleware(['auth:api', 'checkrole:gerente,operativo,superadmin']);

        // Usuarios (Superadmin only)
        Route::apiResource('users', \App\Http\Controllers\UserController::class)
            ->middleware(['auth:api', 'checkrole:superadmin']);
        
        // Pending count (dashboard related)
        Route::get('/uploads/pending-count', [\App\Http\Controllers\ClientUploadController::class, 'pendingCount'])
            ->middleware(['auth:api', 'checkrole:gerente,operativo,superadmin']);
        
        // Uploads group with granular auth
        Route::prefix('uploads')->middleware(['auth:api', 'checkrole'])->group(function () {
            Route::get('/', [\App\Http\Controllers\ClientUploadController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\ClientUploadController::class, 'store'])
                ->middleware('checkrole:cliente');
            Route::get('/{id}/download', [\App\Http\Controllers\ClientUploadController::class, 'download'])
                ->middleware('checkrole:operativo,gerente,superadmin');
            Route::post('/{id}/validate', [\App\Http\Controllers\ClientUploadController::class, 'validateUpload'])
                ->middleware('checkrole:operativo');
            Route::post('/{id}/approve', [\App\Http\Controllers\ClientUploadController::class, 'approveUpload'])
                ->middleware('checkrole:gerente');
            Route::delete('/{id}', [\App\Http\Controllers\ClientUploadController::class, 'destroy'])
                ->middleware('checkrole:cliente,superadmin');
        });

Route::get('/debug-passport', function (Illuminate\Http\Request $request) {
    return [
        'user' => $request->user('api') ? $request->user('api')->email : 'NADIE',
        'auth_header' => $request->header('Authorization'),
        'all_headers' => $request->headers->all(),
        'guard_api_driver' => config('auth.guards.api.driver'),
    ];
});

use App\Http\Controllers\ContableImportController;
use App\Http\Controllers\ContableController;

Route::prefix('contable')->middleware('auth:api')->group(function () {
    Route::post('/upload/{type}', [ContableImportController::class, 'upload'])->middleware('checkrole:cliente,superadmin');
    
    Route::middleware('checkrole:gerente,operativo,superadmin')->group(function () {
        Route::get('/facturas', [ContableController::class, 'getFacturas']);
        Route::get('/bancos', [ContableController::class, 'getBancos']);
        Route::get('/auxiliar', [ContableController::class, 'getAuxiliares']);
        Route::get('/gastos', [ContableController::class, 'getGastos']);
        Route::get('/imports', [ContableController::class, 'getImports']);
        Route::delete('/clear', [ContableController::class, 'clearAll']);
        Route::post('/reconcile', [App\Http\Controllers\ReconciliationController::class, 'reconcile']);
    });
});

Route::post('/settlement/reconcile', [\App\Http\Controllers\SettlementController::class, 'reconcile'])
    ->middleware(['auth:api', 'checkrole:operativo,superadmin']);

Route::post('/conciliacion-susuerte', [\App\Http\Controllers\ConciliationController::class, 'conciliate'])
    ->middleware(['auth:api', 'checkrole:operativo,superadmin,gerente,contable']);

use App\Http\Controllers\PlanillaController;

Route::prefix('planilla')->middleware('auth:api')->group(function () {
    Route::middleware('checkrole:cliente,superadmin')->group(function () {
        Route::post('/fincas', [PlanillaController::class, 'storeFinca']);
        Route::post('/trabajadores', [PlanillaController::class, 'storeTrabajador']);
        Route::post('/labores', [PlanillaController::class, 'storeLabor']);
        Route::post('/actividades', [PlanillaController::class, 'storeActividad']);
        Route::post('/gastos', [PlanillaController::class, 'storeGasto']);
    });

    Route::middleware('checkrole:gerente,operativo,superadmin')->group(function () {
        Route::get('/fincas', [PlanillaController::class, 'getFincas']);
        Route::get('/trabajadores', [PlanillaController::class, 'getTrabajadores']);
        Route::get('/labores', [PlanillaController::class, 'getLabores']);
        Route::get('/actividades', [PlanillaController::class, 'getActividades']);
        Route::delete('/actividades/{id}', [PlanillaController::class, 'deleteActividad']);
        Route::get('/gastos', [PlanillaController::class, 'getGastos']);
        Route::get('/resumen', [PlanillaController::class, 'getResumen']);
    });
});
Route::get('/document-types', function() { return \App\Models\DocumentType::all(); })->middleware(['auth:api']);

use App\Http\Controllers\MandatoController;

Route::prefix('mandatos')->middleware('auth:api')->group(function () {
    Route::post('/', [MandatoController::class, 'store'])->middleware('checkrole:cliente');
    Route::get('/', [MandatoController::class, 'index'])->middleware('checkrole:cliente,gerente,operativo,superadmin');
    Route::patch('/{id}/status', [MandatoController::class, 'updateStatus'])->middleware('checkrole:operativo,superadmin');
});

// Parámetros Genéricos (Superadmin)
Route::prefix('parameters')->middleware(['auth:api'])->group(function () {
    Route::get('/{table}', [\App\Http\Controllers\ParameterController::class, 'index']);
    Route::post('/{table}', [\App\Http\Controllers\ParameterController::class, 'store'])->middleware('checkrole:superadmin');
    Route::put('/{table}/{id}', [\App\Http\Controllers\ParameterController::class, 'update'])->middleware('checkrole:superadmin');
    Route::delete('/{table}/{id}', [\App\Http\Controllers\ParameterController::class, 'destroy'])->middleware('checkrole:superadmin');
});

// Documentos Internos (Staff Flow)
Route::prefix('internal-docs')->middleware(['auth:api', 'checkrole:operativo,contable,gerente,superadmin'])->group(function () {
    Route::get('/', [\App\Http\Controllers\InternalDocumentController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\InternalDocumentController::class, 'store']);
    Route::patch('/{id}/status', [\App\Http\Controllers\InternalDocumentController::class, 'updateStatus']);
    Route::delete('/{id}', [\App\Http\Controllers\InternalDocumentController::class, 'destroy']);
});
