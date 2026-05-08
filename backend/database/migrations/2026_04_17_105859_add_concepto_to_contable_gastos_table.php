<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contable_gastos', function (Blueprint $table) {
            $table->string('concepto')->nullable()->after('tercero');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contable_gastos', function (Blueprint $table) {
            $table->dropColumn('concepto');
        });
    }
};
