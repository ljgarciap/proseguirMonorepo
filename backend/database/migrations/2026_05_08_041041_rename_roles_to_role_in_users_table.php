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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->after('password')->nullable();
        });
        
        // Data migration if needed, but we are doing migrate:fresh anyway
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->after('password')->nullable();
            $table->dropColumn('role');
        });
    }
};
