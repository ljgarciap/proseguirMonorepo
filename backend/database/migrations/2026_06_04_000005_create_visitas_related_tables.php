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
        // 1. Tipo Creditos
        Schema::create('tipo_creditos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo')->unique();
            $table->timestamps();
        });

        // 2. Amortizaciones
        Schema::create('amortizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo')->unique();
            $table->timestamps();
        });

        // 3. Visitas
        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('ciudad');
            $table->unsignedBigInteger('cliente_id');
            $table->string('asistentes');
            $table->text('observaciones')->nullable();
            $table->boolean('requiere_credito')->default(false);

            // Credit Details
            $table->unsignedBigInteger('tipo_credito_id')->nullable();
            $table->decimal('monto_solicitado', 15, 2)->nullable();
            $table->integer('plazo')->nullable();
            $table->unsignedBigInteger('amortizacion_id')->nullable();
            $table->text('destino_recurso')->nullable();
            $table->string('garantia')->nullable();
            $table->string('fuente_pago')->nullable();

            $table->timestamps();

            // Foreign Keys
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('cascade');
            $table->foreign('tipo_credito_id')->references('id')->on('tipo_creditos')->onDelete('set null');
            $table->foreign('amortizacion_id')->references('id')->on('amortizaciones')->onDelete('set null');
        });

        // Seed initial values
        DB::table('tipo_creditos')->insert([
            ['nombre' => 'Crédito Ordinario', 'codigo' => 'ORDINARIO', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Crédito Constructor', 'codigo' => 'CONSTRUCTOR', 'created_at' => now(), 'updated_at' => now()]
        ]);

        DB::table('amortizaciones')->insert([
            ['nombre' => 'Diario', 'codigo' => 'DIARIO', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Quincenal', 'codigo' => 'QUINCENAL', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Mensual', 'codigo' => 'MENSUAL', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Trimestral', 'codigo' => 'TRIMESTRAL', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Semestral', 'codigo' => 'SEMESTRAL', 'created_at' => now(), 'updated_at' => now()]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitas');
        Schema::dropIfExists('amortizaciones');
        Schema::dropIfExists('tipo_creditos');
    }
};
