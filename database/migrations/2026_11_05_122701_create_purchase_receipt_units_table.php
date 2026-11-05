<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links each received device to the invoice price it was acquired at.
     */
    public function up(): void
    {
        Schema::create('purchase_receipt_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_unit_id')->unique()->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->decimal('unit_cost', 15, 2);
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_units');
    }
};
