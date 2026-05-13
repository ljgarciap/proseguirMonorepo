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
        Schema::create('internal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->string('target_role'); // contable, gerente
            $table->string('titulo');
            $table->string('archivo_path');
            $table->foreignId('categoria_id')->constrained('accounting_categories')->onDelete('cascade');
            $table->foreignId('prioridad_id')->constrained('accounting_priorities')->onDelete('cascade');
            $table->enum('estado', ['pendiente', 'visto', 'procesado', 'rechazado'])->default('pendiente');
            $table->text('mensaje')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_documents');
    }
};
