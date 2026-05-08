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
        Schema::table('pago_factorings', function (Blueprint $table) {
            $table->decimal('dias_pagos', 15, 2)->nullable();
            $table->decimal('dias_sobrantes', 15, 2)->nullable();
            $table->decimal('intereses_diarios', 15, 2)->nullable();
            $table->decimal('intereses_pagados', 15, 2)->nullable();
            $table->decimal('devolucion_descuento', 15, 2)->nullable();
            $table->decimal('margen_reserva', 15, 2)->nullable();
            $table->string('estado_liquidacion')->default('pendiente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pago_factorings', function (Blueprint $table) {
            $table->dropColumn([
                'dias_pagos',
                'dias_sobrantes',
                'intereses_diarios',
                'intereses_pagados',
                'devolucion_descuento',
                'margen_reserva',
                'estado_liquidacion'
            ]);
        });
    }
};
