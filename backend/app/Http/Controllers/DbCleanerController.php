<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class DbCleanerController extends Controller
{
    // Define table groupings by module
    protected $modules = [
        'factoring' => [
            'operacion_factorings',
            'pago_factorings',
            'operacion_confirmings',
            'operacion_carteras',
            'compraventas',
            'pagos_compraventa',
            'client_uploads',
            'clientes'
        ],
        'contable' => [
            'contable_facturas',
            'contable_bancos',
            'contable_auxiliars',
            'contable_gastos',
            'contable_imports'
        ],
        'planilla' => [
            'planilla_fincas',
            'planilla_trabajadors',
            'planilla_labors',
            'planilla_actividads',
            'planilla_gastos'
        ],
        'creditos' => [
            'credito_ordinarios'
        ],
        'mandatos' => [
            'mandatos'
        ],
        'internal_docs' => [
            'internal_documents'
        ],
        'notificaciones' => [
            'notificaciones',
            'destinatarios'
        ],
        'system_logs' => [
            'system_logs'
        ]
    ];

    /**
     * Clear specific tables grouped by module
     */
    public function clearTables(Request $request)
    {
        $request->validate([
            'modules' => 'required|array',
            'modules.*' => 'string|in:' . implode(',', array_keys($this->modules))
        ]);

        $selectedModules = $request->modules;
        $tablesToClear = [];

        foreach ($selectedModules as $module) {
            $tablesToClear = array_merge($tablesToClear, $this->modules[$module]);
        }

        Schema::disableForeignKeyConstraints();

        foreach ($tablesToClear as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        return response()->json([
            'message' => 'Tablas de los módulos seleccionados vaciadas correctamente.',
            'modules' => $selectedModules
        ]);
    }

    /**
     * Total database reset: truncates all tables and runs the initial seeds
     */
    public function resetDatabase()
    {
        Schema::disableForeignKeyConstraints();

        // 1. Gather all tables to truncate (application tables)
        $allApplicationTables = [];
        foreach ($this->modules as $tables) {
            $allApplicationTables = array_merge($allApplicationTables, $tables);
        }

        // Add core tables that need to be cleared and re-seeded
        $coreTablesToSeed = [
            'users',
            'document_types',
            'sectores',
            'sector_mappings',
            'accounting_categories',
            'accounting_priorities'
        ];

        $tablesToTruncate = array_merge($allApplicationTables, $coreTablesToSeed);

        // Truncate all collected tables
        foreach ($tablesToTruncate as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        // 2. Re-run initial seeds
        try {
            Artisan::call('db:seed', ['--force' => true]);
            $output = Artisan::output();
            
            return response()->json([
                'message' => 'Base de datos reiniciada y semillas aplicadas correctamente.',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al ejecutar las semillas: ' . $e->getMessage()
            ], 500);
        }
    }
}
