<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Claims a normalized supplier invoice number for one correction chain the first time
     * an invoice with it is posted, so only a linked correction can reuse a reversed number.
     */
    public function up(): void
    {
        Schema::create('supplier_invoice_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_supplier_id')->constrained()->restrictOnDelete();
            $table->string('number_key', 64)->collation('utf8mb4_bin');
            $table->foreignId('root_purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['operational_supplier_id', 'number_key'], 'supplier_invoice_numbers_claim_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_numbers');
    }
};
