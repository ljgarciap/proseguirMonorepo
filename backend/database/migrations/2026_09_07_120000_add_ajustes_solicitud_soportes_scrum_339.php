<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SCRUM-339: la pantalla "Solicitud de Documentos" (Etapa 1, Director de
 * Crédito) necesita:
 * - Una sola observación a nivel de TODA la solicitud (no por documento,
 *   decisión de Luis 2026-09-07) — no existía ninguna columna para esto en
 *   document_requests (solo document_request_items.observaciones, que es
 *   el motivo de rechazo de UN documento, semántica distinta).
 * - Documentos ad-hoc agregados por el Director ("+ Agregar documento")
 *   que NO se registran en el catálogo compartido document_requirements
 *   (decisión de Luis: quedan aislados a esta solicitud puntual, para no
 *   ensuciar el catálogo con nombres puntuales) — de ahí que
 *   document_requirement_id deba poder ser null, con el nombre/descripción
 *   guardados directo en el item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->text('observaciones')->nullable()->after('preset_nombre');
        });

        Schema::table('document_request_items', function (Blueprint $table) {
            $table->string('nombre_personalizado')->nullable()->after('document_requirement_id');
            $table->text('descripcion_personalizada')->nullable()->after('nombre_personalizado');
        });

        // document_requirement_id era NOT NULL (constrained() sin
        // ->nullable() en la migración original) — hay que reconstruir la
        // FK para permitir null en los items ad-hoc.
        Schema::table('document_request_items', function (Blueprint $table) {
            $table->dropForeign(['document_requirement_id']);
        });

        Schema::table('document_request_items', function (Blueprint $table) {
            $table->unsignedBigInteger('document_requirement_id')->nullable()->change();
            $table->foreign('document_requirement_id')->references('id')->on('document_requirements')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('document_request_items', function (Blueprint $table) {
            $table->dropForeign(['document_requirement_id']);
        });

        Schema::table('document_request_items', function (Blueprint $table) {
            $table->unsignedBigInteger('document_requirement_id')->nullable(false)->change();
            $table->foreign('document_requirement_id')->references('id')->on('document_requirements')->onDelete('cascade');
        });

        Schema::table('document_request_items', function (Blueprint $table) {
            $table->dropColumn(['nombre_personalizado', 'descripcion_personalizada']);
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropColumn('observaciones');
        });
    }
};
