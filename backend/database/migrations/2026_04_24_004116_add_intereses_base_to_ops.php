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
        Schema::table('operacion_factorings', function (Blueprint $table) {
            $table->decimal('intereses_diarios', 15, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacion_factorings', function (Blueprint $table) {
            $table->dropColumn('intereses_diarios');
        });
    }
};
