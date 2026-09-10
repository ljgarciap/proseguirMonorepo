<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-347: 'correo_notificacion' ahora puede traer varios
     * destinatarios separados por coma/punto y coma (ver
     * App\Rules\MultipleEmails) — un string() (VARCHAR 255) es corto para
     * varias direcciones de correo reales, mismo riesgo de "Data too long
     * for column" que SCRUM-348 si la lista de destinatarios crece.
     */
    public function up(): void
    {
        Schema::table('solicitudes_credito', function (Blueprint $table) {
            $table->text('correo_notificacion')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_credito', function (Blueprint $table) {
            $table->string('correo_notificacion')->change();
        });
    }
};
