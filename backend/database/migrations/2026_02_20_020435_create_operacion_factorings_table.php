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
        Schema::create('operacion_factorings', function (Blueprint $table) {
            $table->id();
            $table->string('operacion')->nullable();
            $table->string('cliente')->nullable();
            $table->string('nit_cliente')->nullable();
            $table->string('factura_numero')->nullable();
            $table->decimal('monto', 15, 2)->nullable();
            $table->decimal('dias', 8, 2)->nullable();
            $table->decimal('tasa_descuento', 8, 2)->nullable();
            $table->string('pagador')->nullable();
            $table->string('nit_pagador')->nullable();
            $table->string('fecha_aprobacion')->nullable();
            $table->decimal('valor_aprobado', 15, 2)->nullable();
            $table->decimal('valor_desembolsado', 15, 2)->nullable();
            $table->string('fecha_desembolso')->nullable();
            $table->string('fecha_vencimiento')->nullable();
            $table->decimal('valor_reserva', 15, 2)->nullable();
            $table->decimal('descuento_financiero', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operacion_factorings');
    }
};
