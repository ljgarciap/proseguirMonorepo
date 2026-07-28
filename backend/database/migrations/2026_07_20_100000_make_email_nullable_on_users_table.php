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
        // SCRUM-147: el formulario de creación de usuario ya trata el correo
        // como opcional (validación 'nullable'), pero la columna real era
        // NOT NULL. Al enviar el formulario sin correo, el middleware
        // ConvertEmptyStringsToNull convierte '' en null y el INSERT fallaba
        // con "Column 'email' cannot be null" (500 genérico para el
        // usuario). MySQL permite múltiples NULL en una columna UNIQUE, así
        // que no hace falta tocar la validación ni el índice.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
