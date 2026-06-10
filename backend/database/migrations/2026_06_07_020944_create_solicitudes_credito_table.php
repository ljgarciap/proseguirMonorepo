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
        Schema::create('solicitudes_credito', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visita_id')->nullable();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('usuario_registra_id');
            $table->unsignedBigInteger('tipo_credito_id');
            $table->decimal('monto_solicitado', 15, 2);
            $table->integer('plazo_meses');
            $table->unsignedBigInteger('amortizacion_id');
            $table->text('destino_recurso');
            $table->string('garantia')->nullable();
            $table->string('fuente_pago');
            $table->string('correo_notificacion');
            $table->string('asunto_notificacion');
            $table->text('mensaje_notificacion');
            $table->unsignedBigInteger('document_preset_id')->nullable();
            $table->timestamps();

            $table->foreign('visita_id')->references('id')->on('visitas')->onDelete('set null');
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('cascade');
            $table->foreign('usuario_registra_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('tipo_credito_id')->references('id')->on('tipo_creditos')->onDelete('cascade');
            $table->foreign('amortizacion_id')->references('id')->on('amortizaciones')->onDelete('cascade');
            $table->foreign('document_preset_id')->references('id')->on('document_presets')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_credito');
    }
};
