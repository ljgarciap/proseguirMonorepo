<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_compraventa', function (Blueprint $table) {
            $table->id();
            $table->string('pago_ref')->nullable();
            $table->string('pagador')->nullable();
            $table->string('nit_pagador')->nullable();
            $table->string('cliente')->nullable();
            $table->string('nit_cliente')->nullable();
            $table->string('concepto')->nullable();
            $table->string('estado')->nullable();
            $table->string('fecha_recaudo')->nullable();
            // Detalle por operación
            $table->string('op')->nullable();
            $table->string('id_titulo')->nullable();
            $table->decimal('valor_factura', 18, 2)->nullable();
            $table->string('fec_inicial')->nullable();
            $table->string('fec_final')->nullable();
            $table->string('dias')->nullable();
            $table->string('factor')->nullable();
            $table->decimal('saldo_capital', 18, 2)->nullable();
            $table->decimal('valor_descuento', 18, 2)->nullable();
            $table->decimal('capital_pagado', 18, 2)->nullable();
            $table->decimal('total_pagado', 18, 2)->nullable();
            // Totales del documento
            $table->decimal('total_recaudo', 18, 2)->nullable();
            $table->decimal('valor_recaudado', 18, 2)->nullable();
            $table->string('observaciones')->nullable();
            $table->foreignId('client_upload_id')->nullable()->constrained('client_uploads')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_compraventa');
    }
};
