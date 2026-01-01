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
        Schema::create('wholesale_products', function (Blueprint $table) {
            $table->id();
            $table->decimal('price', 10, 2);
            $table->integer('min_qty')->default(1);
            $table->integer('max_qty')->default(10);

            $table->boolean('is_active')->default(true);

            $table->foreignId('currency_id')->constrained();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Add indexes for better performance
            $table->index(['product_id']);
            $table->index('is_active');
            $table->index(['min_qty', 'max_qty']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wholesale_products');
    }
};
