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
        Schema::create('accounting_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('color')->default('#000000');
            $table->integer('horas_vencimiento')->default(24);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_priorities');
    }
};
