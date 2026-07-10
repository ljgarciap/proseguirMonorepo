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
        Schema::create('document_envio_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envio_id')->constrained('document_envios')->onDelete('cascade');
            $table->unsignedInteger('orden');
            $table->foreignId('area_id')->constrained('document_areas')->onDelete('cascade');
            $table->enum('estado', ['pendiente', 'en_proceso', 'procesado', 'devuelto'])->default('pendiente');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_procesamiento')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_envio_steps');
    }
};
