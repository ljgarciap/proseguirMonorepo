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
        Schema::create('operacion_confirmings', function (Blueprint $table) {
            $table->id();
            $table->string('operacion')->nullable();
            $table->string('emisor')->nullable();
            $table->string('emisor_nit')->nullable();
            $table->string('deudor')->nullable();
            $table->string('deudor_nit')->nullable();
            $table->decimal('tasa_factor', 8, 2)->nullable();
            $table->string('id_titulo')->nullable();
            $table->string('fecha_inicial')->nullable();
            $table->string('fecha_final')->nullable();
            $table->decimal('dias', 8, 2)->nullable();
            $table->decimal('valor_nominal', 15, 2)->nullable();
            $table->decimal('reembolso_g_desembolso', 15, 2)->nullable();
            $table->decimal('base_negociacion', 15, 2)->nullable();
            $table->decimal('rendimientos_proyectados', 15, 2)->nullable();
            $table->decimal('valor_pagar_deudor', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operacion_confirmings');
    }
};
