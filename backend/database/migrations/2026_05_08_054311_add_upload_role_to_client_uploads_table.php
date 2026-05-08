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
        Schema::table('client_uploads', function (Blueprint $table) {
            $table->string('upload_role')->default('cliente')->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_uploads', function (Blueprint $table) {
            $table->dropColumn('upload_role');
        });
    }
};
