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
        $exactCollation = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            ? 'utf8mb4_bin'
            : null;

        Schema::create('product_definitions', function (Blueprint $table) use ($exactCollation) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('model')->nullable();
            $table->string('tracking_mode', 32)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('sku')->nullable()->collation($exactCollation)->unique();
            $table->string('barcode')->nullable()->collation($exactCollation)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_definitions');
    }
};
