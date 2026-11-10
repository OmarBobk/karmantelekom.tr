<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The confirmed storefront order a counter checkout collects at pickup. Only a linked
     * checkout may sell stock that order holds.
     */
    public function up(): void
    {
        Schema::table('checkout_attempts', function (Blueprint $table) {
            $table->foreignId('storefront_order_id')->nullable()->after('network_reservation_id')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Refused while any checkout is linked to a storefront order.
     */
    public function down(): void
    {
        if (DB::table('checkout_attempts')->whereNotNull('storefront_order_id')->exists()) {
            throw new RuntimeException('Phase 7.5 rollback refused: checkouts linked to storefront orders exist. Nothing was changed. Fix forward with a new migration instead of rolling back.');
        }

        Schema::table('checkout_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storefront_order_id');
        });
    }
};
