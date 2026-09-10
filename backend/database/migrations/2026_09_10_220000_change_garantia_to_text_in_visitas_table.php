<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditoría post-SCRUM-348: 'garantia' en 'visitas' es el mismo campo de
     * negocio, la misma entrada de UI (<input type="text"> sin maxlength,
     * ver VisitasComponent) y la misma validación backend sin límite
     * ('nullable|string', VisitaController::store()/update()) que
     * solicitudes_credito.garantia — nunca se llegó a romper en producción
     * solo porque nadie pegó todavía un texto de garantías/avalistas largo
     * en el formulario de Visita, no porque el campo esté protegido. Mismo
     * fix preventivo, antes de que ocurra.
     */
    public function up(): void
    {
        Schema::table('visitas', function (Blueprint $table) {
            $table->text('garantia')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visitas', function (Blueprint $table) {
            $table->string('garantia')->nullable()->change();
        });
    }
};
