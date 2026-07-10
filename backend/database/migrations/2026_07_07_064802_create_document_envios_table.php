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
        Schema::create('document_envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->string('titulo');
            $table->foreignId('categoria_id')->constrained('accounting_categories')->onDelete('cascade');
            $table->foreignId('prioridad_id')->constrained('accounting_priorities')->onDelete('cascade');
            $table->text('observaciones')->nullable();
            $table->enum('estado_general', ['pendiente', 'en_proceso', 'procesado', 'devuelto'])->default('pendiente');
            $table->unsignedInteger('current_step_order')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_envios');
    }
};
