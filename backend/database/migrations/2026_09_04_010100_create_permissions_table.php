<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor paramétrico de Roles y Permisos — Fase 1 (ver
 * docs/specs/rbac-roles-permisos-parametrico.md). Catálogo de permisos por
 * pantalla/módulo completo (granularidad confirmada con Luis) — una fila
 * por cada ruta protegida hoy en app.routes.ts + entradas backend-only sin
 * pantalla propia. Ninguna ruta/endpoint real lee esta tabla todavía
 * (enforcement es Fase 2) — es catálogo puro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            // Agrupador para la UI (ej. "Gestión de Créditos") — varias
            // permissions pueden compartir módulo.
            $table->string('modulo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
