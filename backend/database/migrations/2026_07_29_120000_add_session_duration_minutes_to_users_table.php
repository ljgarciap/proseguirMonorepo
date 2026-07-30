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
        // SCRUM-161: el usuario puede elegir cuánto dura su sesión (token de
        // acceso) de una lista cerrada de opciones (ver config('auth.session_duration_options')).
        // Default 480 min (8h) — mismo orden de magnitud que el TTL que ya
        // vivía implícito en Passport antes de este ticket para tokens
        // personales de uso diario.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('session_duration_minutes')->nullable()->default(480)->after('roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('session_duration_minutes');
        });
    }
};
