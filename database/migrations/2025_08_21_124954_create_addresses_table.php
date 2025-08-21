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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->string('label'); // e.g., "Head Office", "Branch", "Warehouse"
            $table->text('address_line');
            $table->string('city');
            $table->string('postal_code')->nullable();
            $table->string('state')->nullable();
            $table->decimal('latitude', 10, 8)->nullable(); // for map
            $table->decimal('longitude', 11, 8)->nullable(); // for map
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
