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
        Schema::create('analisis_financieros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_ordinario_id')->unique()->constrained('credito_ordinarios')->onDelete('cascade');
            $table->string('estado')->default('borrador'); // borrador, confirmado

            $table->unsignedSmallInteger('anio_inicial')->nullable();
            $table->unsignedTinyInteger('cantidad_anios')->nullable(); // 2 o 3 (SCRUM-155)

            // Un solo actor (Coordinador Comercial, a diferencia de Informe
            // Técnico) — cada pestaña queda en JSON: inputs crudos + totales,
            // análisis estructural y variación relativa ya calculados por
            // AnalisisFinancieroCalculoService (nunca confiar en lo que
            // mande el frontend, mismo criterio que InformeTecnico).
            $table->json('activo')->nullable();
            $table->json('pasivo')->nullable();
            $table->json('patrimonio')->nullable();
            $table->json('utilidad_neta')->nullable();
            $table->json('ori')->nullable();
            $table->json('cartera')->nullable();

            $table->text('observaciones')->nullable();
            $table->string('soporte_complementario')->nullable();

            $table->foreignId('diligenciado_por_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('diligenciado_por_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analisis_financieros');
    }
};
