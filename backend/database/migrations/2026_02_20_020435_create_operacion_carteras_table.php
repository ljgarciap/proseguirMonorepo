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
        Schema::create('operacion_carteras', function (Blueprint $table) {
            $table->id();
            $table->string('cliente')->nullable();
            $table->string('identificacion')->nullable();
            $table->string('actividad_economica')->nullable();
            $table->string('operacion')->nullable();
            $table->decimal('saldo_total', 15, 2)->nullable();
            $table->decimal('plazo_meses', 8, 2)->nullable();
            $table->decimal('tasa_interes', 8, 2)->nullable();
            $table->string('plan_amortizacion')->nullable();
            $table->text('garantia_detalle')->nullable();
            $table->string('estado_garantia')->nullable();
            $table->string('tipo_garantia')->nullable();
            $table->string('fecha_desembolso')->nullable();
            $table->string('numero_radicado')->nullable();
            $table->string('estado_capital')->nullable();
            $table->string('fecha_vencimiento_capital')->nullable();
            $table->decimal('valor_desembolso', 15, 2)->nullable();
            $table->decimal('saldo_capital', 15, 2)->nullable();
            $table->string('vencido')->nullable();
            $table->decimal('dias_vencido', 8, 2)->nullable();
            $table->decimal('valor_vencido', 15, 2)->nullable();
            $table->string('tiene_mora')->nullable();
            $table->decimal('valor_mora', 15, 2)->nullable();
            $table->string('fecha_ultimo_abono')->nullable();
            $table->decimal('valor_ultimo_abono', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operacion_carteras');
    }
};
