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
        Schema::create('pago_factorings', function (Blueprint $table) {
            $table->id();
            $table->string('pago_nro')->nullable();
            $table->string('fecha_pago')->nullable();
            $table->string('cliente')->nullable();
            $table->string('nit')->nullable();
            $table->string('reliquidacion')->nullable();
            $table->string('fecha_reliquidacion')->nullable();
            $table->string('op_relacionada')->nullable();
            $table->string('factura_nro')->nullable();
            $table->string('cc_o_nit')->nullable();
            $table->string('pagador')->nullable();
            $table->string('fecha_inicial')->nullable();
            $table->string('fecha_final')->nullable();
            $table->decimal('dias_cartera', 8, 2)->nullable();
            $table->decimal('valor_titulo', 15, 2)->nullable();
            $table->decimal('valor_nominal', 15, 2)->nullable();
            $table->decimal('descuento_financiero', 15, 2)->nullable();
            $table->decimal('monto_pagado', 15, 2)->nullable();
            $table->decimal('saldo_restante', 15, 2)->nullable();
            $table->decimal('total_recaudado_comprobante', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pago_factorings');
    }
};
