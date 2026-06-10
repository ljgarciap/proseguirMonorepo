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
        Schema::create('credito_ordinarios', function (Blueprint $table) {
            $table->id();
            $table->string('numero_solicitud')->unique();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->decimal('monto', 15, 2);
            $table->integer('plazo_meses');
            $table->string('estado'); // e.g. revision_documental, comite_evaluacion, etc.
            $table->json('documentos')->nullable(); // JSON map of files uploaded
            $table->json('historial_estados')->nullable(); // JSON log of state transitions
            $table->timestamps();

            // Foreign key to users
            $table->foreign('cliente_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credito_ordinarios');
    }
};
