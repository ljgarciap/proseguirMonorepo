<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conciliaciones_susuerte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('conciliated_at');
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->integer('matched_count')->default(0);
            $table->integer('generated_gastos')->default(0);
            $table->json('details')->nullable(); // full payload of the conciliation
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conciliaciones_susuerte');
    }
};
