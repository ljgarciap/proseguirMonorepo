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
        Schema::table('clientes', function (Blueprint $table) {
            // Documento y tipo persona
            $table->unsignedBigInteger('tipo_persona_id')->nullable()->after('id');
            $table->unsignedBigInteger('tipo_documento_id')->nullable()->after('tipo_persona_id');
            $table->string('numero_documento')->nullable()->after('identificacion');

            // Persona Natural
            $table->string('nombres')->nullable();
            $table->string('primer_apellido')->nullable();
            $table->string('segundo_apellido')->nullable();
            $table->string('correo_electronico')->nullable();
            $table->string('ocupacion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('pais')->nullable();
            $table->string('departamento')->nullable();
            $table->string('direccion')->nullable();

            // Persona Jurídica
            $table->string('nombre_razon_social')->nullable();
            $table->string('tipo_empresa')->nullable();
            $table->string('correo_electronico_empresarial')->nullable();
            $table->string('pagina_web')->nullable();

            // Representante Legal (Persona Jurídica)
            $table->unsignedBigInteger('rep_tipo_documento_id')->nullable();
            $table->string('rep_numero_documento')->nullable();
            $table->string('rep_nombres')->nullable();
            $table->string('rep_primer_apellido')->nullable();
            $table->string('rep_segundo_apellido')->nullable();
            $table->string('rep_cargo')->nullable();
            $table->string('rep_correo_electronico')->nullable();
            $table->string('rep_telefono')->nullable();

            // Estado activo
            $table->boolean('activo')->default(true)->after('verification_method');

            // Foreign keys
            $table->foreign('tipo_persona_id')->references('id')->on('tipo_personas')->onDelete('set null');
            $table->foreign('tipo_documento_id')->references('id')->on('document_types')->onDelete('set null');
            $table->foreign('rep_tipo_documento_id')->references('id')->on('document_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['tipo_persona_id']);
            $table->dropForeign(['tipo_documento_id']);
            $table->dropForeign(['rep_tipo_documento_id']);

            $table->dropColumn([
                'tipo_persona_id',
                'tipo_documento_id',
                'numero_documento',
                'nombres',
                'primer_apellido',
                'segundo_apellido',
                'correo_electronico',
                'ocupacion',
                'telefono',
                'pais',
                'departamento',
                'direccion',
                'nombre_razon_social',
                'tipo_empresa',
                'correo_electronico_empresarial',
                'pagina_web',
                'rep_tipo_documento_id',
                'rep_numero_documento',
                'rep_nombres',
                'rep_primer_apellido',
                'rep_segundo_apellido',
                'rep_cargo',
                'rep_correo_electronico',
                'rep_telefono',
                'activo'
            ]);
        });
    }
};
