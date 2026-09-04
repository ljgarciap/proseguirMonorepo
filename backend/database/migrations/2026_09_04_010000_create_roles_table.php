<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor paramétrico de Roles y Permisos — Fase 1 (ver
 * docs/specs/rbac-roles-permisos-parametrico.md). Catálogo de roles,
 * independiente de la columna users.roles (json) que sigue siendo la
 * fuente de verdad real de autorización — ver la nota de corrección en la
 * spec sobre por qué no se migra esa columna en esta fase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->string('descripcion')->nullable();
            // Roles semilla (los 10 que ya existen hardcodeados hoy): slug
            // protegido, no editable desde la UI — código legacy
            // (CheckUserRole, resolveActiveRole, whereJsonContains) sigue
            // comparando ese string literal mientras Fase 2 no exista.
            $table->boolean('es_sistema')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
