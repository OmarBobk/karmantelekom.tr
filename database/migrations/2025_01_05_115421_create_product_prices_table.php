<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('price_type')->default('wholesale'); // e.g., wholesale, retail
            $table->decimal('price', 10, 2);
            $table->string('currency', 10)->default('TRY');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_prices');
    }
};
