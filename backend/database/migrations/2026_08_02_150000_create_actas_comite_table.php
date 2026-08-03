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
        Schema::create('actas_comite', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero')->unique(); // consecutivo global, sin reinicio anual (SCRUM-169)
            $table->string('estado')->default('pendiente'); // pendiente, borrador, aprobada

            $table->date('fecha_reunion')->nullable();
            $table->text('lugar')->nullable(); // rich text (HTML) con imágenes inline
            $table->time('hora_inicio')->nullable();
            $table->time('hora_finalizacion')->nullable();

            $table->json('asistentes')->nullable(); // [{nombre}]
            $table->json('orden_dia')->nullable(); // [{id, texto, orden}], arranca con los 7 ítems predeterminados
            $table->boolean('orden_dia_aprobado')->default(false);
            $table->json('desarrollo')->nullable(); // {orden_dia_id: html}
            $table->text('observaciones_generales')->nullable(); // rich text
            $table->json('firmantes')->nullable(); // [{nombre, rol}]

            $table->foreignId('elaborada_por_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('registrada_por_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('registrada_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actas_comite');
    }
};
