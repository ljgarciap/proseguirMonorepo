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
        Schema::table('solicitudes_credito', function (Blueprint $table) {
            // Requerido solo para Crédito Constructor (SCRUM-120 Fase 2) —
            // nullable porque las solicitudes Ordinario no lo usan.
            $table->string('proyecto')->nullable()->after('tipo_credito_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_credito', function (Blueprint $table) {
            $table->dropColumn('proyecto');
        });
    }
};
