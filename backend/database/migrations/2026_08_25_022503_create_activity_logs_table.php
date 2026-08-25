<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SCRUM-246 — Log de actividad de usuarios (eventos de negocio, no cada
 * request HTTP). Distinto de `system_logs` (pipeline de OCR) y de
 * `firmas_electronicas` (evidencia legal de firma, SCRUM-245, con trigger
 * de BD porque ahí la inmutabilidad tiene peso legal). Acá el objetivo es
 * un feed de auditoría operativa para superadmin — a nivel de aplicación
 * nunca se expone update/delete sobre este modelo, pero no lleva trigger
 * de BD: no tiene el mismo peso de evidencia legal que una firma, agregar
 * el trigger acá sería exceso de ingeniería para lo que pide este ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Nullable: un intento de login fallido no tiene usuario
            // autenticado todavía. nombre_usuario es snapshot (igual
            // criterio que FirmaElectronica) para que un cambio de nombre
            // posterior no reescriba la historia.
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre_usuario', 150)->nullable();

            $table->string('accion', 100);
            $table->text('descripcion');

            // Entidad afectada (polimórfica "manual", sin morphTo — acá no
            // hace falta cargar la relación, solo mostrarla en la UI).
            $table->string('entidad_type')->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();

            $table->string('direccion_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['accion']);
            $table->index(['usuario_id']);
            $table->index(['entidad_type', 'entidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
