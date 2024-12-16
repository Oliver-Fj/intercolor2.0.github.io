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
        Schema::create('page_contents', function (Blueprint $table) {
            $table->id();
            $table->string('page_name'); // inicio, productos, nosotros, contacto
            $table->string('section_name'); // slider, banner, header, etc.
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('season')->default('default'); // navidad, fiestas_patrias, default
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->json('additional_data')->nullable(); // Para datos extras como botones, links, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_contents');
    }
};
