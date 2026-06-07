<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipo_personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo')->unique();
            $table->timestamps();
        });

        // Seed initial values
        DB::table('tipo_personas')->insert([
            [
                'nombre' => 'Persona Natural',
                'codigo' => 'NATURAL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'Persona Jurídica',
                'codigo' => 'JURIDICA',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_personas');
    }
};
