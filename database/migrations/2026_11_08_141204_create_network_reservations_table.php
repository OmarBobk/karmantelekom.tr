<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requests by one branch to hold another branch's shared device, and their fulfillment.
     *
     * active_unit_id holds the device id only while a reservation is approved, so the unique
     * index allows one active reservation per device. owning_store_id stays the original owner
     * after a transfer moves the device to the requesting branch.
     */
    public function up(): void
    {
        Schema::create('network_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_listing_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('listing_revision');
            $table->foreignId('inventory_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('owning_store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('requesting_store_id')->constrained('stores')->restrictOnDelete();
            $table->string('status', 16);
            $table->unsignedBigInteger('active_unit_id')->nullable()->unique();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->string('request_note', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->foreignId('sale_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('fulfilled_store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index(['owning_store_id', 'status']);
            $table->index(['requesting_store_id', 'status']);
            $table->index(['inventory_unit_id', 'status']);
        });
    }

    /**
     * Refuses to drop recorded reservations; fix forward with a new migration instead.
     */
    public function down(): void
    {
        if (Schema::hasTable('network_reservations') && DB::table('network_reservations')->exists()) {
            throw new RuntimeException('Phase 7 rollback refused: Store Network reservations exist. Nothing was changed. Fix forward with a new migration instead of rolling back.');
        }

        Schema::dropIfExists('network_reservations');
    }
};
