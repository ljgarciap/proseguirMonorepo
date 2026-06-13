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
        Schema::create('datos_factor', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social');
            $table->string('tipo_documento');
            $table->string('numero_documento');
            $table->string('rep_legal_nombre');
            $table->string('rep_legal_tipo_doc');
            $table->string('rep_legal_num_doc');
            $table->string('rep_legal_email');
            $table->timestamps();
        });

        // Insert initial default values
        \Illuminate\Support\Facades\DB::table('datos_factor')->insert([
            'razon_social' => 'PROSEGUIR SOLUCIONES DE LIQUIDEZ SAS',
            'tipo_documento' => 'NIT',
            'numero_documento' => '900354306-2',
            'rep_legal_nombre' => 'PAULA TATIANA HOYOS GIRALDO',
            'rep_legal_tipo_doc' => 'CC',
            'rep_legal_num_doc' => '30402881',
            'rep_legal_email' => 'gerencia@proseguirliquidez.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datos_factor');
    }
};
