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
        Schema::table('operacion_carteras', function (Blueprint $table) {
            $table->text('observaciones')->nullable()->after('valor_ultimo_abono'); // Assuming it goes at the end or after something relevant
            $table->string('ciudad')->nullable()->after('numero_radicado');
            $table->string('sector_economico')->nullable()->after('actividad_economica');
            
            // Re-check existing table and drop legacy ones
            // Keep tipo_garantia and estado_garantia as per latest user feedback
            $table->dropColumn(['operacion', 'saldo_total']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacion_carteras', function (Blueprint $table) {
            $table->dropColumn(['observaciones', 'ciudad', 'sector_economico']);
            $table->string('operacion')->nullable();
            $table->decimal('saldo_total', 15, 2)->nullable();
        });
    }
};
