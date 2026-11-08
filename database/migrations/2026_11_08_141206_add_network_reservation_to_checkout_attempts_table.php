<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Store Network reservation a checkout fulfills. Only a linked checkout may sell an
     * actively reserved device.
     */
    public function up(): void
    {
        Schema::table('checkout_attempts', function (Blueprint $table) {
            $table->foreignId('network_reservation_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Refused while any checkout is linked to a reservation.
     */
    public function down(): void
    {
        if (DB::table('checkout_attempts')->whereNotNull('network_reservation_id')->exists()) {
            throw new RuntimeException('Phase 7 rollback refused: checkouts linked to Store Network reservations exist. Nothing was changed. Fix forward with a new migration instead of rolling back.');
        }

        Schema::table('checkout_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('network_reservation_id');
        });
    }
};
