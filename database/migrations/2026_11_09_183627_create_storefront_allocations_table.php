<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock a branch makes eligible for the storefront: an online quantity of a quantity-tracked
     * product, or one exact device. Independent of Store Network sharing; never moves stock.
     * `quantity_scope` is 1 only on quantity rows, so a branch has one quantity row per product.
     */
    public function up(): void
    {
        Schema::create('storefront_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_unit_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedTinyInteger('quantity_scope')->nullable()->storedAs('case when `inventory_unit_id` is null then 1 end');
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'product_definition_id', 'quantity_scope'], 'storefront_allocations_quantity_unique');
            $table->index(['product_definition_id', 'quantity']);
        });
    }

    /**
     * Refuses to drop recorded allocations; fix forward with a new migration instead.
     */
    public function down(): void
    {
        if (Schema::hasTable('storefront_allocations') && DB::table('storefront_allocations')->exists()) {
            throw new RuntimeException('Phase 7.5 rollback refused: storefront allocations exist. Nothing was changed. Fix forward with a new migration instead of rolling back.');
        }

        Schema::dropIfExists('storefront_allocations');
    }
};
