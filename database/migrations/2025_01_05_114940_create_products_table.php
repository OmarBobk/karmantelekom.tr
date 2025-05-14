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
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');

            // Turkish translations
            $table->string('tr_name')->nullable();
            $table->text('tr_description')->nullable();
            $table->string('tr_slug')->nullable();

            // Arabic translations
            $table->string('ar_name')->nullable();
            $table->text('ar_description')->nullable();
            $table->string('ar_slug')->nullable();

            $table->timestamps();

            // Add indexes for better performance
            $table->index('name');
            $table->index('code');
            $table->index('serial');
            $table->index('is_active');
            $table->index('slug');
            $table->index('tr_slug');
            $table->index('ar_slug');

        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
