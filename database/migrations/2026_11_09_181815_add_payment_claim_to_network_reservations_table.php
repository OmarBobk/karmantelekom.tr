<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The linked checkout whose payment started on a reservation. While that checkout is open
     * and frozen, it alone may sell the device, even after the hold's nominal expiry.
     */
    public function up(): void
    {
        Schema::table('network_reservations', function (Blueprint $table) {
            $table->foreignId('claimed_attempt_id')->nullable()->after('expires_at')->constrained('checkout_attempts')->restrictOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('claimed_attempt_id');
        });
    }

    /**
     * Refused while any reservation holds or held a payment claim.
     */
    public function down(): void
    {
        if (DB::table('network_reservations')->whereNotNull('claimed_attempt_id')->exists()) {
            throw new RuntimeException('Phase 7 rollback refused: Store Network reservations hold payment claims. Nothing was changed. Fix forward with a new migration instead of rolling back.');
        }

        Schema::table('network_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claimed_attempt_id');
            $table->dropColumn('claimed_at');
        });
    }
};
