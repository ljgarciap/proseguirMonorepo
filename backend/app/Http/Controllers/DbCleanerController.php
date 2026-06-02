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
            'destinatarios',
            're_notificacion_destinatario'
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
        try {
            // Re-run all migrations from scratch and seed initial database data
            Artisan::call('migrate:fresh', [
                '--force' => true,
                '--seed' => true
            ]);

            // Reinstall passport to recreate OAuth clients
            Artisan::call('passport:install', [
                '--force' => true,
                '--no-interaction' => true
            ]);

            $output = Artisan::output();
            
            return response()->json([
                'message' => 'Base de datos reiniciada, migraciones ejecutadas y semillas aplicadas correctamente.',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al reiniciar la base de datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Diagnose and repair missing database tables in production/local environment
     */
    public function repairSchema()
    {
        $missingTables = [];

        // Collect all tables defined in modules
        foreach ($this->modules as $tables) {
            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    $missingTables[] = $table;
                }
            }
        }

        // Collect core tables
        $coreTables = [
            'users',
            'document_types',
            'sectores',
            'sector_mappings',
            'accounting_categories',
            'accounting_priorities'
        ];
        foreach ($coreTables as $table) {
            if (!Schema::hasTable($table)) {
                $missingTables[] = $table;
            }
        }

        if (empty($missingTables)) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Todas las tablas requeridas están presentes en la base de datos.'
            ]);
        }

        // Find associated migrations for missing tables
        $migrationFiles = glob(database_path('migrations/*.php'));
        $migrationsToReset = [];

        foreach ($missingTables as $table) {
            foreach ($migrationFiles as $file) {
                $filename = basename($file);
                if (str_contains($filename, "create_{$table}_table") || 
                    str_contains($filename, "create_{$table}s_table") ||
                    str_contains($filename, "create_{$table}") ||
                    ($table === 'notificaciones' && str_contains($filename, 'notifications')) ||
                    ($table === 'destinatarios' && str_contains($filename, 'notifications')) ||
                    ($table === 're_notificacion_destinatario' && str_contains($filename, 'notifications'))
                ) {
                    $migrationsToReset[] = pathinfo($filename, PATHINFO_FILENAME);
                }
            }
        }

        $migrationsToReset = array_unique($migrationsToReset);

        if (!empty($migrationsToReset)) {
            // Delete records of these migrations from migrations table so Laravel will run them again
            DB::table('migrations')->whereIn('migration', $migrationsToReset)->delete();

            try {
                // Re-run pending migrations
                Artisan::call('migrate', ['--force' => true]);
                $output = Artisan::output();

                return response()->json([
                    'status' => 'repaired',
                    'message' => 'Se detectaron y repararon tablas faltantes exitosamente.',
                    'missing_tables' => $missingTables,
                    'repaired_migrations' => $migrationsToReset,
                    'output' => $output
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al ejecutar las migraciones de reparación: ' . $e->getMessage(),
                    'missing_tables' => $missingTables
                ], 500);
            }
        }

        return response()->json([
            'status' => 'missing_no_migrations',
            'message' => 'Se detectaron tablas faltantes pero no se encontraron sus migraciones asociadas.',
            'missing_tables' => $missingTables
        ], 400);
    }
}
