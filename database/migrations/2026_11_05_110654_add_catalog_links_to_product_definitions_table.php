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
        Schema::table('product_definitions', function (Blueprint $table) {
            $table->foreignId('catalog_brand_id')->nullable()->after('model')->constrained('catalog_brands')->restrictOnDelete();
            $table->foreignId('catalog_category_id')->nullable()->after('catalog_brand_id')->constrained('catalog_categories')->restrictOnDelete();
            $table->index('name');
            $table->index('model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_definitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_category_id');
            $table->dropConstrainedForeignId('catalog_brand_id');
            $table->dropIndex(['name']);
            $table->dropIndex(['model']);
        });
    }
};
