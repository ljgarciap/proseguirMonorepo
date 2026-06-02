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
        // 1. Requisitos de Documentos (Paramétrico)
        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 2. Presets de Documentos (Paramétrico)
        Schema::create('document_presets', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        // 3. Tabla Pivote Preset - Requisito
        Schema::create('document_preset_requirement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preset_id')->constrained('document_presets')->onDelete('cascade');
            $table->foreignId('requirement_id')->constrained('document_requirements')->onDelete('cascade');
            $table->timestamps();
        });

        // 4. Solicitudes de Documentos a Clientes
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('creado_por')->constrained('users')->onDelete('cascade');
            $table->string('estado')->default('pendiente'); // pendiente, completado
            $table->timestamps();
        });

        // 5. Items/Requisitos específicos de la Solicitud
        Schema::create('document_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained('document_requests')->onDelete('cascade');
            $table->foreignId('document_requirement_id')->constrained('document_requirements')->onDelete('cascade');
            $table->unsignedBigInteger('client_upload_id')->nullable();
            $table->string('estado')->default('pendiente'); // pendiente, subido, aprobado, rechazado
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('client_upload_id')->references('id')->on('client_uploads')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_request_items');
        Schema::dropIfExists('document_requests');
        Schema::dropIfExists('document_preset_requirement');
        Schema::dropIfExists('document_presets');
        Schema::dropIfExists('document_requirements');
    }
};
