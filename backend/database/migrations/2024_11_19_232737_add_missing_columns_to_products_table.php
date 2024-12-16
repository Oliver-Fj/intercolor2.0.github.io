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
        Schema::table('products', function (Blueprint $table) {
            // Agregar status si no existe
            if (!Schema::hasColumn('products', 'status')) {
                $table->string('status')->default('active');
            }

            // Agregar is_public si no existe
            if (!Schema::hasColumn('products', 'is_public')) {
                $table->boolean('is_public')->default(true);
            }
            
            /* // Agregar category_id si no existe
            if (!Schema::hasColumn('products', 'category_id')) {
                $table->foreignId('category_id')->nullable()->constrained();
            } */
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['status', 'is_public']);
           /*  $table->dropForeign(['category_id']);
            $table->dropColumn('category_id'); */
        });
    }
};
