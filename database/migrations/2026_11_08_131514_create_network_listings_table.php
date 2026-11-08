<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One Store Network listing per device, kept when withdrawn so republishing reuses it.
     */
    public function up(): void
    {
        Schema::create('network_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_unit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at');
            $table->foreignId('unpublished_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('unpublished_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();

            $table->index(['unpublished_at', 'store_id']);
        });
    }

    /**
     * Refuses to drop recorded listings; fix forward with a new migration instead.
     */
    public function down(): void
    {
        if (Schema::hasTable('network_listings') && DB::table('network_listings')->exists()) {
            throw new RuntimeException('Phase 7 rollback refused: Store Network listings exist. Nothing was changed. Fix forward with a new migration instead of rolling back.');
        }

        Schema::dropIfExists('network_listings');
    }
};
