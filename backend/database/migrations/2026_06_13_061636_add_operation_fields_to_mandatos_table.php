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
        Schema::table('mandatos', function (Blueprint $table) {
            $table->string('monto_estimado_total')->nullable()->after('factor_rep_legal_email');
            $table->string('plazo_estimado')->nullable()->after('monto_estimado_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mandatos', function (Blueprint $table) {
            $table->dropColumn(['monto_estimado_total', 'plazo_estimado']);
        });
    }
};
