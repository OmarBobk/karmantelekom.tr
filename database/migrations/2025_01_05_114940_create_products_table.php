<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->unique();
            $table->string('serial')->nullable()->unique();
            $table->text('description');
            $table->boolean('is_active')->default(true);
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Add indexes for better performance
            $table->index('name');
            $table->index('code');
            $table->index('serial');
            $table->index('is_active');
            $table->index('slug');

        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
