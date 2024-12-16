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
        Schema::table('products', function (Blueprint $table) {
            // Primero añadimos status
            $table->string('status')->default('active');
            // Luego añadimos is_public
            $table->boolean('is_public')->default(true);
        });

        // Actualizar productos existentes
        DB::table('products')->update([
            'status' => 'active',
            'is_public' => true
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['status', 'is_public']);
        });
    }
};
