<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanSlateService
{
    /**
     * Truncate all operational tables and the client master table.
     */
    public static function execute()
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'operacion_carteras',
            'operacion_factorings',
            'pago_factorings',
            'operacion_confirmings',
            'clientes',
            'system_logs'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        return true;
    }
}
