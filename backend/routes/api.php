<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);


Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:api');
Route::patch('/profile', [AuthController::class, 'updateProfile'])->middleware('auth:api');
Route::get('/profile/session-duration-options', [AuthController::class, 'sessionDurationOptions'])->middleware('auth:api');

Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'stats'])->middleware(['auth:api', 'checkrole:gerente,operativo,superadmin']);

// SCRUM-230 — pestaña "Cartera Factoring": Operativo consulta, Superadmin además exporta (ver tabla de actores del ticket).
Route::get('/dashboard/cartera-factoring', [\App\Http\Controllers\DashboardController::class, 'carteraFactoring'])->middleware(['auth:api', 'checkrole:operativo,superadmin']);
Route::get('/dashboard/cartera-factoring/export', [\App\Http\Controllers\DashboardController::class, 'exportCarteraFactoringExcel'])->middleware(['auth:api', 'checkrole:superadmin']);

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

        Route::delete('/history/by-file', [\App\Http\Controllers\HistoryController::class, 'deleteByFile'])
            ->middleware(['auth:api', 'checkrole:gerente,operativo,superadmin']);

        // Usuarios (Superadmin only)
        Route::post('users/{id}/restore', [\App\Http\Controllers\UserController::class, 'restore'])
            ->middleware(['auth:api', 'checkrole:superadmin']);
        Route::apiResource('users', \App\Http\Controllers\UserController::class)
            ->middleware(['auth:api', 'checkrole:superadmin']);
        
        // Clientes (Superadmin, Gerente, Operativo, Coordinador Comercial — SCRUM-149)
        Route::get('clientes', [\App\Http\Controllers\ClienteController::class, 'index'])
            ->middleware(['auth:api', 'checkrole:superadmin,gerente,operativo,coordinador_comercial']);
        Route::post('clientes/quick', [\App\Http\Controllers\ClienteController::class, 'quickStore'])
            ->middleware(['auth:api', 'checkrole:superadmin,gerente,operativo,coordinador_comercial']);
        Route::apiResource('clientes', \App\Http\Controllers\ClienteController::class)->except(['index'])
            ->middleware(['auth:api', 'checkrole:superadmin,gerente,operativo,coordinador_comercial']);
        
        // Visitas a Clientes (Superadmin, Gerente, Operativo)
        Route::apiResource('visitas', \App\Http\Controllers\VisitaController::class)
            ->middleware(['auth:api', 'checkrole:superadmin,gerente,operativo']);

        // Departamentos/Ciudades de Colombia (SCRUM-118)
        // Coordinador Comercial además lo necesita para los selects de
        // ubicación (cliente y proyecto) en Registro Solicitud de Crédito
        // (SCRUM-118 / SCRUM-141).
        Route::prefix('ubicaciones')->middleware(['auth:api', 'checkrole:superadmin,gerente,operativo,coordinador_comercial'])->group(function () {
            Route::get('/departamentos', [\App\Http\Controllers\UbicacionController::class, 'departamentos']);
            Route::get('/ciudades', [\App\Http\Controllers\UbicacionController::class, 'ciudades']);
            Route::get('/ciudades/buscar', [\App\Http\Controllers\UbicacionController::class, 'buscarCiudades']);
        });
        
        // Destinatarios (Superadmin only)
        Route::apiResource('destinatarios', \App\Http\Controllers\DestinatarioController::class)
            ->middleware(['auth:api', 'checkrole:superadmin']);
        
        // Notificaciones (Superadmin only)
        Route::apiResource('notificaciones', \App\Http\Controllers\NotificacionController::class)
            ->middleware(['auth:api', 'checkrole:superadmin']);
        
        // Asignaciones (Superadmin only)
        Route::get('/asignaciones', [\App\Http\Controllers\AsignacionController::class, 'index'])
            ->middleware(['auth:api', 'checkrole:superadmin']);
        Route::get('/asignaciones/{id}', [\App\Http\Controllers\AsignacionController::class, 'show'])
            ->middleware(['auth:api', 'checkrole:superadmin']);
        Route::post('/asignaciones', [\App\Http\Controllers\AsignacionController::class, 'store'])
            ->middleware(['auth:api', 'checkrole:superadmin']);
        Route::delete('/asignaciones/{id}', [\App\Http\Controllers\AsignacionController::class, 'destroy'])
            ->middleware(['auth:api', 'checkrole:superadmin']);
        
        // Pending count (dashboard related) — el propio controller ya calcula
        // y devuelve el contador de 'contable' (bandeja interna), así que ese
        // rol también necesita poder pegarle a este endpoint.
        Route::get('/uploads/pending-count', [\App\Http\Controllers\ClientUploadController::class, 'pendingCount'])
            ->middleware(['auth:api', 'checkrole:gerente,operativo,contable,superadmin']);
        
        // Uploads group with granular auth
        Route::prefix('uploads')->middleware(['auth:api', 'checkrole'])->group(function () {
            Route::get('/', [\App\Http\Controllers\ClientUploadController::class, 'index']);
            Route::get('/recent-ocr', [\App\Http\Controllers\ClientUploadController::class, 'recentOcr']);
            Route::post('/', [\App\Http\Controllers\ClientUploadController::class, 'store'])
                ->middleware('checkrole:cliente,operativo');
            // coordinador_comercial: SCRUM-191 — revisa desde Gestión de
            // Créditos los documentos que el cliente reenvió tras un
            // resultado "Pendiente por Comité".
            Route::get('/{id}/download', [\App\Http\Controllers\ClientUploadController::class, 'download'])
                ->middleware('checkrole:cliente,operativo,gerente,coordinador_comercial,superadmin');
            Route::post('/{id}/validate', [\App\Http\Controllers\ClientUploadController::class, 'validateUpload'])
                ->middleware('checkrole:operativo');
            Route::post('/{id}/approve', [\App\Http\Controllers\ClientUploadController::class, 'approveUpload'])
                ->middleware('checkrole:gerente');
            Route::delete('/{id}', [\App\Http\Controllers\ClientUploadController::class, 'destroy'])
                ->middleware('checkrole:cliente,superadmin');
        });

use App\Http\Controllers\ContableImportController;
use App\Http\Controllers\ContableController;

Route::prefix('contable')->middleware('auth:api')->group(function () {
    Route::post('/upload/{type}', [ContableImportController::class, 'upload'])->middleware('checkrole:cliente,superadmin');
    Route::delete('/clear', [ContableController::class, 'clearAll'])->middleware('checkrole:superadmin');
    
    Route::middleware('checkrole:gerente,operativo,superadmin')->group(function () {
        Route::get('/facturas', [ContableController::class, 'getFacturas']);
        Route::get('/bancos', [ContableController::class, 'getBancos']);
        Route::get('/auxiliar', [ContableController::class, 'getAuxiliares']);
        Route::get('/gastos', [ContableController::class, 'getGastos']);
        Route::get('/imports', [ContableController::class, 'getImports']);
        Route::post('/reconcile', [App\Http\Controllers\ReconciliationController::class, 'reconcile']);
    });
});

Route::post('/settlement/reconcile', [\App\Http\Controllers\SettlementController::class, 'reconcile'])
    ->middleware(['auth:api', 'checkrole:operativo,superadmin']);

Route::post('/conciliacion-susuerte', [\App\Http\Controllers\ConciliationController::class, 'conciliate'])
    ->middleware(['auth:api', 'checkrole:operativo,superadmin,gerente,contable']);

Route::get('/conciliaciones-susuerte/history', [\App\Http\Controllers\ConciliationController::class, 'history'])
    ->middleware(['auth:api', 'checkrole:operativo,superadmin,gerente,contable']);
Route::post('/conciliaciones-susuerte/new', [\App\Http\Controllers\ConciliationController::class, 'newConciliation'])
    ->middleware(['auth:api', 'checkrole:operativo,superadmin,gerente,contable']);
Route::put('/conciliaciones-susuerte/{id}', [\App\Http\Controllers\ConciliationController::class, 'update'])
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
use App\Http\Controllers\CreditoOrdinarioController;

Route::prefix('mandatos')->middleware('auth:api')->group(function () {
    Route::post('/', [MandatoController::class, 'store'])->middleware('checkrole:cliente');
    Route::get('/', [MandatoController::class, 'index'])->middleware('checkrole:cliente,gerente,operativo,superadmin');
    Route::patch('/{id}/status', [MandatoController::class, 'updateStatus'])->middleware('checkrole:operativo,superadmin');
    Route::get('/{id}/export', [MandatoController::class, 'export'])->middleware('checkrole:cliente,gerente,operativo,contable,superadmin');
    Route::put('/{id}', [MandatoController::class, 'update'])->middleware('checkrole:operativo,superadmin');
    Route::delete('/{id}', [MandatoController::class, 'destroy'])->middleware('checkrole:operativo,superadmin');
});

// Créditos Ordinarios (Proceso BPMN)
Route::prefix('creditos')->middleware('auth:api')->group(function () {
    Route::get('/', [CreditoOrdinarioController::class, 'index']);
    Route::post('/', [CreditoOrdinarioController::class, 'store']);
    Route::get('/{id}', [CreditoOrdinarioController::class, 'show']);
    Route::post('/{id}/transition', [CreditoOrdinarioController::class, 'transition']);
});

// Informe Técnico — Crédito Constructor (SCRUM-120)
Route::prefix('informes-tecnicos')->middleware('auth:api')->group(function () {
    Route::get('/', [\App\Http\Controllers\InformeTecnicoController::class, 'index']);
    Route::get('/{creditoId}', [\App\Http\Controllers\InformeTecnicoController::class, 'show']);
    Route::put('/{creditoId}/borrador', [\App\Http\Controllers\InformeTecnicoController::class, 'guardarBorrador']);
    Route::post('/{creditoId}/registrar', [\App\Http\Controllers\InformeTecnicoController::class, 'registrar']);
    Route::get('/{creditoId}/descargar', [\App\Http\Controllers\InformeTecnicoController::class, 'descargar']);
});

// Análisis Financiero (SCRUM-155)
Route::prefix('analisis-financiero')->middleware('auth:api')->group(function () {
    Route::get('/', [\App\Http\Controllers\AnalisisFinancieroController::class, 'index']);
    Route::get('/{creditoId}', [\App\Http\Controllers\AnalisisFinancieroController::class, 'show']);
    Route::put('/{creditoId}/borrador', [\App\Http\Controllers\AnalisisFinancieroController::class, 'guardarBorrador']);
    Route::post('/{creditoId}/confirmar', [\App\Http\Controllers\AnalisisFinancieroController::class, 'confirmar']);
    Route::get('/{creditoId}/descargar', [\App\Http\Controllers\AnalisisFinancieroController::class, 'descargar']);
    Route::post('/{creditoId}/adjuntos', [\App\Http\Controllers\AnalisisFinancieroController::class, 'subirAdjunto']);
    Route::delete('/{creditoId}/adjuntos/{index}', [\App\Http\Controllers\AnalisisFinancieroController::class, 'eliminarAdjunto']);
});

// Actas del Comité de Crédito (SCRUM-169)
Route::prefix('actas-comite')->middleware('auth:api')->group(function () {
    Route::get('/', [\App\Http\Controllers\ActaComiteController::class, 'index']);
    Route::post('/generar', [\App\Http\Controllers\ActaComiteController::class, 'generar']);
    Route::get('/{acta}', [\App\Http\Controllers\ActaComiteController::class, 'show']);
    Route::put('/{acta}', [\App\Http\Controllers\ActaComiteController::class, 'actualizar']);
    Route::post('/{acta}/aprobar-orden-dia', [\App\Http\Controllers\ActaComiteController::class, 'aprobarOrdenDia']);
    Route::get('/{acta}/creditos-elegibles', [\App\Http\Controllers\ActaComiteController::class, 'buscarCreditosElegibles']);
    Route::post('/{acta}/solicitudes', [\App\Http\Controllers\ActaComiteController::class, 'agregarSolicitud']);
    Route::put('/{acta}/solicitudes/{solicitud}', [\App\Http\Controllers\ActaComiteController::class, 'actualizarSolicitud']);
    Route::delete('/{acta}/solicitudes/{solicitud}', [\App\Http\Controllers\ActaComiteController::class, 'eliminarSolicitud']);
    Route::post('/{acta}/solicitudes/{solicitud}/presentacion', [\App\Http\Controllers\ActaComiteController::class, 'subirPresentacion']);
    Route::post('/{acta}/imagenes', [\App\Http\Controllers\ActaComiteController::class, 'subirImagen']);
    Route::get('/{acta}/previsualizar', [\App\Http\Controllers\ActaComiteController::class, 'previsualizar']);
    Route::get('/{acta}/descargar', [\App\Http\Controllers\ActaComiteController::class, 'descargar']);
    Route::post('/{acta}/registrar', [\App\Http\Controllers\ActaComiteController::class, 'registrar']);
});

// Listas Restrictivas y SARLAFT (SCRUM-128)
Route::prefix('listas-sarlaft')->middleware('auth:api')->group(function () {
    Route::get('/', [\App\Http\Controllers\ListasRestrictivasSarlaftController::class, 'index']);
    Route::get('/{creditoId}', [\App\Http\Controllers\ListasRestrictivasSarlaftController::class, 'show']);
    Route::put('/{creditoId}/borrador', [\App\Http\Controllers\ListasRestrictivasSarlaftController::class, 'guardarBorrador']);
    Route::post('/{creditoId}/finalizar', [\App\Http\Controllers\ListasRestrictivasSarlaftController::class, 'finalizar']);
});

// Gestión de Créditos (SCRUM-178). SCRUM-211/215/219 amplían el acceso de
// módulo a Gerente y Operativo — la restricción fina de qué puede ver/hacer
// cada rol dentro del módulo vive dentro del controlador (ROLES_POR_CLAVE,
// autorizarAccionGerencial()/autorizarAccionOperativa()), no acá.
Route::prefix('gestion-creditos')->middleware(['auth:api', 'checkrole:superadmin,coordinador_comercial,gerente,operativo,tesoreria'])->group(function () {
    Route::get('/', [\App\Http\Controllers\GestionCreditoController::class, 'index']);
    Route::get('/tarjetas', [\App\Http\Controllers\GestionCreditoController::class, 'tarjetas']);
    Route::get('/{creditoId}', [\App\Http\Controllers\GestionCreditoController::class, 'show']);
    Route::post('/{creditoId}/notificar', [\App\Http\Controllers\GestionCreditoController::class, 'notificar']);
    // SCRUM-191: documentos reenviados por el cliente tras "Pendiente por Comité".
    Route::get('/{creditoId}/documentos', [\App\Http\Controllers\GestionCreditoController::class, 'documentosPendientes']);
    Route::post('/{creditoId}/documentos/{itemId}/revisar', [\App\Http\Controllers\GestionCreditoController::class, 'revisarDocumento']);
    // SCRUM-205: Formalización de Garantías (validación por ítem).
    Route::get('/{creditoId}/formalizacion-garantias', [\App\Http\Controllers\GestionCreditoController::class, 'formalizacionGarantias']);
    Route::post('/{creditoId}/formalizacion-garantias', [\App\Http\Controllers\GestionCreditoController::class, 'guardarFormalizacionGarantias']);
    // SCRUM-193: Registro de Crédito en CYF (fecha + radicado).
    Route::post('/{creditoId}/registro-cyf', [\App\Http\Controllers\GestionCreditoController::class, 'registroCyf']);
    // SCRUM-211: aprobación (Gerente) del Registro de Crédito en CYF.
    Route::post('/{creditoId}/aprobacion-registro-cyf', [\App\Http\Controllers\GestionCreditoController::class, 'aprobacionRegistroCyf']);
    // SCRUM-215: registro (Operativo) de la Operación de Desembolso en CYF.
    Route::post('/{creditoId}/desembolso-ingreso', [\App\Http\Controllers\GestionCreditoController::class, 'desembolsoIngreso']);
    // SCRUM-219: aprobación (Gerente) del Registro de Operación de Desembolso en CYF.
    Route::post('/{creditoId}/desembolso-aprobacion', [\App\Http\Controllers\GestionCreditoController::class, 'desembolsoAprobacion']);
    // SCRUM-224: registro (Tesorería) de la Transferencia Bancaria del desembolso.
    Route::post('/{creditoId}/transferencia-bancaria', [\App\Http\Controllers\GestionCreditoController::class, 'transferenciaBancaria']);
});

// Registro de Solicitudes de Crédito
Route::prefix('solicitudes-credito')->middleware('auth:api')->group(function () {
    Route::get('/pendientes', [\App\Http\Controllers\SolicitudCreditoController::class, 'indexPending'])
        ->middleware('checkrole:superadmin,gerente,coordinador_comercial,operativo');
    Route::get('/', [\App\Http\Controllers\SolicitudCreditoController::class, 'index'])
        ->middleware('checkrole:superadmin,gerente,coordinador_comercial,operativo');
    Route::post('/', [\App\Http\Controllers\SolicitudCreditoController::class, 'store'])
        ->middleware('checkrole:superadmin,gerente,coordinador_comercial,operativo');
    // SCRUM-159: edición de "Condiciones Financieras del Crédito" restringida a
    // Coordinador Comercial (superadmin siempre pasa por CheckUserRole::handle).
    Route::put('/{solicitudCredito}', [\App\Http\Controllers\SolicitudCreditoController::class, 'update'])
        ->middleware('checkrole:coordinador_comercial');
});

// Parámetros Genéricos (Superadmin)
Route::prefix('parameters')->middleware(['auth:api'])->group(function () {
    Route::get('/{table}', [\App\Http\Controllers\ParameterController::class, 'index']);
    Route::post('/{table}', [\App\Http\Controllers\ParameterController::class, 'store'])->middleware('checkrole:superadmin');
    Route::put('/{table}/{id}', [\App\Http\Controllers\ParameterController::class, 'update'])->middleware('checkrole:superadmin');
    Route::delete('/{table}/{id}', [\App\Http\Controllers\ParameterController::class, 'destroy'])->middleware('checkrole:superadmin');
});

// Datos Factor (Mandatos)
Route::get('/datos-factor', [\App\Http\Controllers\DatosFactorController::class, 'show'])
    ->middleware(['auth:api', 'checkrole:cliente,operativo,gerente,superadmin']);
Route::put('/datos-factor', [\App\Http\Controllers\DatosFactorController::class, 'update'])
    ->middleware(['auth:api', 'checkrole:operativo,gerente,superadmin']);

// Documentos Internos (Staff Flow)
Route::prefix('internal-docs')->middleware(['auth:api', 'checkrole:operativo,contable,gerente,superadmin,coordinador_comercial'])->group(function () {
    Route::get('/', [\App\Http\Controllers\InternalDocumentController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\InternalDocumentController::class, 'store']);
    Route::patch('/bulk-status', [\App\Http\Controllers\InternalDocumentController::class, 'bulkUpdateStatus']);
    Route::patch('/{id}/status', [\App\Http\Controllers\InternalDocumentController::class, 'updateStatus']);
    Route::delete('/bulk-delete', [\App\Http\Controllers\InternalDocumentController::class, 'bulkDestroy']);
    Route::delete('/{id}', [\App\Http\Controllers\InternalDocumentController::class, 'destroy']);
});

// Bandeja Interna — Ruta de Aprobación Secuencial (SCRUM-94)
Route::prefix('document-envios')->middleware(['auth:api', 'checkrole:operativo,contable,gerente,superadmin,coordinador_comercial'])->group(function () {
    Route::get('/', [\App\Http\Controllers\DocumentEnvioController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\DocumentEnvioController::class, 'store']);
    Route::patch('/{id}/steps/{stepId}', [\App\Http\Controllers\DocumentEnvioController::class, 'processStep']);
    Route::get('/{id}/files/{fileId}/download', [\App\Http\Controllers\DocumentEnvioController::class, 'downloadFile']);
    Route::delete('/{id}', [\App\Http\Controllers\DocumentEnvioController::class, 'destroy']);
});

// Catálogo de Áreas para la ruta de aprobación (SCRUM-94)
Route::get('/document-areas', [\App\Http\Controllers\DocumentAreaController::class, 'index'])
    ->middleware(['auth:api', 'checkrole:operativo,contable,gerente,superadmin,coordinador_comercial']);
Route::prefix('document-areas')->middleware(['auth:api', 'checkrole:superadmin'])->group(function () {
    Route::post('/', [\App\Http\Controllers\DocumentAreaController::class, 'store']);
    Route::put('/{id}', [\App\Http\Controllers\DocumentAreaController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\DocumentAreaController::class, 'destroy']);
});

// Configuraciones del Sistema (Superadmin)
Route::prefix('configuraciones')->middleware(['auth:api', 'checkrole:superadmin'])->group(function () {
    Route::get('/', [\App\Http\Controllers\ConfiguracionController::class, 'index']);
    Route::put('/{id}', [\App\Http\Controllers\ConfiguracionController::class, 'update']);
});

// Limpieza de Base de Datos (Superadmin)
Route::prefix('db-cleaner')->middleware(['auth:api', 'checkrole:superadmin'])->group(function () {
    Route::post('/clear-tables', [\App\Http\Controllers\DbCleanerController::class, 'clearTables']);
    Route::post('/reset', [\App\Http\Controllers\DbCleanerController::class, 'resetDatabase']);
    Route::post('/repair', [\App\Http\Controllers\DbCleanerController::class, 'repairSchema']);
});

Route::get('/document-requirements/{id}/download-template', [\App\Http\Controllers\DocumentRequirementController::class, 'downloadTemplate'])
    ->middleware(['auth:api']);

// Requisitos de Documentos (Superadmin, Operativo)
Route::prefix('document-requirements')->middleware(['auth:api', 'checkrole:superadmin,operativo'])->group(function () {
    Route::get('/', [\App\Http\Controllers\DocumentRequirementController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\DocumentRequirementController::class, 'store']);
    Route::put('/{id}', [\App\Http\Controllers\DocumentRequirementController::class, 'update']);
    Route::post('/{id}', [\App\Http\Controllers\DocumentRequirementController::class, 'update']); // Fallback for multipart form uploads in PHP
    Route::delete('/{id}', [\App\Http\Controllers\DocumentRequirementController::class, 'destroy']);
});

// Presets de Documentos (Superadmin, Operativo)
Route::prefix('document-presets')->middleware(['auth:api'])->group(function () {
    // Coordinador Comercial necesita leer los presets para el dropdown de
    // Registro Solicitud de Crédito, aunque no gestione el CRUD de presets.
    Route::get('/', [\App\Http\Controllers\DocumentPresetController::class, 'index'])
        ->middleware('checkrole:superadmin,operativo,coordinador_comercial');
    Route::post('/', [\App\Http\Controllers\DocumentPresetController::class, 'store'])
        ->middleware('checkrole:superadmin,operativo');
    Route::put('/{id}', [\App\Http\Controllers\DocumentPresetController::class, 'update'])
        ->middleware('checkrole:superadmin,operativo');
    Route::delete('/{id}', [\App\Http\Controllers\DocumentPresetController::class, 'destroy'])
        ->middleware('checkrole:superadmin,operativo');
});

// Solicitudes de Documentos a Clientes
Route::prefix('document-requests')->middleware(['auth:api'])->group(function () {
    Route::get('/active', [\App\Http\Controllers\DocumentRequestController::class, 'activeRequest']);
    Route::get('/clients', [\App\Http\Controllers\DocumentRequestController::class, 'getClients'])->middleware('checkrole:superadmin,operativo');
    Route::get('/', [\App\Http\Controllers\DocumentRequestController::class, 'index'])->middleware('checkrole:superadmin,operativo');
    Route::post('/', [\App\Http\Controllers\DocumentRequestController::class, 'store'])->middleware('checkrole:superadmin,operativo');
    Route::delete('/{id}', [\App\Http\Controllers\DocumentRequestController::class, 'destroy'])->middleware('checkrole:superadmin,operativo');
});

// SCRUM-245 — Firma Electrónica (arquitectura genérica, ningún módulo
// conectado todavía — ver FirmaElectronicaController::TIPOS_FIRMABLES).
// La autorización por rol es por documento (Firmable::rolesAutorizadosParaFirmar),
// no por ruta, por eso acá solo se exige sesión autenticada.
// SCRUM-246 — Log de actividad de usuarios (solo superadmin, ver spec).
Route::prefix('activity-logs')->middleware(['auth:api', 'checkrole:superadmin'])->group(function () {
    Route::get('/', [\App\Http\Controllers\ActivityLogController::class, 'index']);
    Route::get('/acciones', [\App\Http\Controllers\ActivityLogController::class, 'acciones']);
});

Route::prefix('firmas')->middleware(['auth:api'])->group(function () {
    // Literal 'verificar/{firma}' va ANTES del comodín '{tipo}/{id}' — con
    // ambas rutas de 2 segmentos, Laravel resuelve por orden de
    // declaración y 'verificar' matchearía como {tipo} si quedara después.
    Route::get('/verificar/{firma}', [\App\Http\Controllers\FirmaElectronicaController::class, 'verificar']);
    Route::post('/{tipo}/{id}/firmar', [\App\Http\Controllers\FirmaElectronicaController::class, 'firmar']);
    Route::get('/{tipo}/{id}', [\App\Http\Controllers\FirmaElectronicaController::class, 'index']);
});
