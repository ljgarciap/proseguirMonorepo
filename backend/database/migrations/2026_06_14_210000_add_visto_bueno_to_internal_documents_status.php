<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE internal_documents MODIFY COLUMN estado ENUM('pendiente', 'visto', 'visto_bueno', 'procesado', 'rechazado') DEFAULT 'pendiente'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE internal_documents MODIFY COLUMN estado ENUM('pendiente', 'visto', 'procesado', 'rechazado') DEFAULT 'pendiente'");
        }
    }
};
