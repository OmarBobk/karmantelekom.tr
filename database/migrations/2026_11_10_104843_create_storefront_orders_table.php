<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A storefront order for linked branch stock, fulfilled by one selected branch. The legacy
     * `orders` row keeps the order history and items; this row holds the branch workflow:
     * pending without a hold, confirmed with holds and a pickup deadline, then fulfilled by a
     * counter sale, cancelled, or expired; or rejected by the branch while pending.
     * `submission_key` makes a repeated submit return the same order. `channel` is the sales
     * channel whose published prices the order uses. A price proposal is identified by
     * `price_proposal_fingerprint`; the buyer accepts exactly that proposal.
     */
    public function up(): void
    {
        Schema::create('storefront_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->char('submission_key', 64);
            $table->string('status', 20)->default('pending');
            $table->string('channel', 20);
            $table->char('currency', 3);
            $table->decimal('total', 12, 2);
            $table->string('notes', 1000)->nullable();
            $table->foreignId('price_change_requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('price_change_requested_at')->nullable();
            $table->unsignedInteger('price_proposal_version')->default(0);
            $table->char('price_proposal_fingerprint', 64)->nullable();
            $table->char('accepted_price_proposal', 64)->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->char('confirmation_fingerprint', 64)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedSmallInteger('pickup_hours')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('sale_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique(['buyer_id', 'submission_key']);
            $table->index(['store_id', 'status']);
        });
    }

    /**
     * Refuses to drop recorded orders; fix forward with a new migration instead.
     */
    public function down(): void
    {
        if (Schema::hasTable('storefront_orders') && DB::table('storefront_orders')->exists()) {
            throw new RuntimeException('Phase 7.5 rollback refused: storefront orders exist. Nothing was changed. Fix forward with a new migration instead of rolling back.');
        }

        Schema::dropIfExists('storefront_orders');
    }
};
