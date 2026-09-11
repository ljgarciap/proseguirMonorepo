<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-345 (rebote): "Anexos" — archivos sueltos sin preset ni clave
     * predefinida (foto de tirilla, Word de valoración, PDF externo,
     * cualquier nombre), cargados por el Director de Crédito y asociados al
     * crédito para consulta interna. El JSON 'documentos' de
     * credito_ordinarios no sirve para esto: es un objeto de claves fijas
     * (una por documento esperado en cada etapa del BPMN, ver
     * CreditoOrdinario::documentosIniciales()), no una lista abierta.
     */
    public function up(): void
    {
        Schema::create('credito_ordinario_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_ordinario_id')->constrained('credito_ordinarios')->cascadeOnDelete();
            $table->string('nombre_original');
            // Ruta relativa al disco 'public' — igual que 'documentos', se
            // resuelve a URL absoluta al leer con el APP_URL vigente
            // (CreditoOrdinario::resolveStorageUrl(), SCRUM-148).
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
            // Quién lo cargó de verdad (auditoría) — a diferencia de
            // ClientUpload, acá no hay "dueño cliente": el anexo es de uso
            // interno, nunca se le atribuye al cliente.
            $table->foreignId('subido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subido_por_rol')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credito_ordinario_anexos');
    }
};
