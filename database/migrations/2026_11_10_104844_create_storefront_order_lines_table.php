<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One priced line of a storefront order, and its stock hold once the branch confirms. A
     * device line is one device. The hold state lives here, not on the order, so every stock
     * check can decide it from line rows locked last: `held_quantity` or `active_unit_id` while
     * held, active until `hold_expires_at` or while a counter checkout holds the payment claim.
     * `active_unit_id` is unique, so one device carries at most one hold.
     */
    public function up(): void
    {
        Schema::create('storefront_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_definition_id')->constrained()->restrictOnDelete();
            $table->string('tracking_mode', 20);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('proposed_unit_price', 12, 2)->nullable();
            $table->foreignId('inventory_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('proposed_unit_id')->nullable()->constrained('inventory_units')->restrictOnDelete();
            $table->unsignedInteger('held_quantity')->default(0);
            $table->foreignId('active_unit_id')->nullable()->unique()->constrained('inventory_units')->restrictOnDelete();
            $table->timestamp('hold_expires_at')->nullable();
            $table->foreignId('claimed_attempt_id')->nullable()->constrained('checkout_attempts')->restrictOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'product_definition_id', 'held_quantity'], 'storefront_order_lines_quantity_holds');
        });
    }

    /**
     * Refuses to drop recorded order lines; fix forward with a new migration instead.
     */
    public function down(): void
    {
        if (Schema::hasTable('storefront_order_lines') && DB::table('storefront_order_lines')->exists()) {
            throw new RuntimeException('Phase 7.5 rollback refused: storefront order lines exist. Nothing was changed. Fix forward with a new migration instead of rolling back.');
        }

        Schema::dropIfExists('storefront_order_lines');
    }
};
