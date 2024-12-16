<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            // Verificamos y agregamos las columnas que faltan
            if (!Schema::hasColumn('categories', 'slug')) {
                $table->string('slug')->after('name')->nullable();
            }
            if (!Schema::hasColumn('categories', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('name');
                $table->foreign('parent_id')
                      ->references('id')
                      ->on('categories')
                      ->onDelete('set null');
            }
            if (!Schema::hasColumn('categories', 'order')) {
                $table->integer('order')->default(0)->after('parent_id');
            }
            if (!Schema::hasColumn('categories', 'active')) {
                $table->boolean('active')->default(true)->after('order');
            }
        });
    }

    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            // Solo quitamos las columnas que agregamos
            if (Schema::hasColumn('categories', 'slug')) {
                $table->dropColumn('slug');
            }
            if (Schema::hasColumn('categories', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            }
            if (Schema::hasColumn('categories', 'order')) {
                $table->dropColumn('order');
            }
            if (Schema::hasColumn('categories', 'active')) {
                $table->dropColumn('active');
            }
        });
    }
};