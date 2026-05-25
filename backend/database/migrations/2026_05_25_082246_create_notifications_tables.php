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
        // 1. Tabla Destinatarios
        Schema::create('destinatarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('email')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 2. Tabla Notificaciones
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('mensaje');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 3. Tabla Pivote re_notificacion_destinatario
        Schema::create('re_notificacion_destinatario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destinatario_id')->constrained('destinatarios')->onDelete('cascade');
            $table->foreignId('notificacion_id')->constrained('notificaciones')->onDelete('cascade');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('re_notificacion_destinatario');
        Schema::dropIfExists('notificaciones');
        Schema::dropIfExists('destinatarios');
    }
};
