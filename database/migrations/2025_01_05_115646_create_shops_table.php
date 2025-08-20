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
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('phone')->nullable();
            $table->string('address');
            $table->string('tax_document_path')->nullable();
            $table->string('business_license_path')->nullable();
            $table->string('tax_number')->nullable();

            $table->json('links')->nullable();

            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->foreignId('salesperson_id')->nullable()->constrained('users');

            $table->index(['owner_id', 'salesperson_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
