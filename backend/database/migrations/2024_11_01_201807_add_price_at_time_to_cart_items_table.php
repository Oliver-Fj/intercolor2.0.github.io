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
        Schema::table('cart_items', function (Blueprint $table) {
            // Añadir el nuevo campo
            $table->decimal('price_at_time', 10, 2)->after('quantity');
            
            // Modificar quantity para tener valor por defecto
            $table->integer('quantity')->default(1)->change();
            
            // Añadir índice único
            $table->unique(['user_id', 'product_id'], 'cart_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // Revertir los cambios
            $table->dropColumn('price_at_time');
            $table->integer('quantity')->change();
            $table->dropUnique('cart_item_unique');
        });
    }
};
