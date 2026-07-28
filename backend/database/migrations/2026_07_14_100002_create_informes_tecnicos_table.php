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
        Schema::create('informes_tecnicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_ordinario_id')->unique()->constrained('credito_ordinarios')->onDelete('cascade');
            $table->string('estado')->default('borrador'); // borrador, registrado

            // Secciones diligenciadas por el rol Ingeniero (SCRUM-120).
            // El detalle de cada sección queda en JSON por ahora — la
            // granularidad exacta de columnas se define en Fase 2 al portar
            // las fórmulas contra el Excel de referencia del ticket.
            $table->json('ventas_totales_proyecto')->nullable();
            $table->json('costos')->nullable();
            $table->json('invertido')->nullable();
            $table->text('observaciones_ingeniero')->nullable();
            $table->foreignId('diligenciado_por_ingeniero_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('diligenciado_por_ingeniero_at')->nullable();

            // Secciones diligenciadas por el rol Coordinador Comercial.
            $table->json('credito_solicitado')->nullable();
            $table->json('saldos_por_recaudar_contraentrega')->nullable();
            $table->json('analisis_financiacion')->nullable();
            $table->json('coberturas')->nullable();
            $table->text('observaciones_coordinador')->nullable();
            $table->foreignId('diligenciado_por_coordinador_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('diligenciado_por_coordinador_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('informes_tecnicos');
    }
};
