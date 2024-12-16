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
        Schema::create('slider_group_slider', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slider_group_id')
                  ->constrained('slider_groups')
                  ->onDelete('cascade');
            $table->foreignId('slider_id')
                  ->constrained('sliders')
                  ->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->timestamps();

             // Evitar duplicados
             $table->unique(['slider_group_id', 'slider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slider_group_slider');
    }
};
