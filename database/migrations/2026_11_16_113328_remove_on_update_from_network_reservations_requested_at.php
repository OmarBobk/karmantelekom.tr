<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MariaDB/MySQL with explicit_defaults_for_timestamp=OFF turns the first non-null
 * TIMESTAMP column into DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP. The
 * original network_reservations migration used $table->timestamp('requested_at')
 * without useCurrent(), so later lifecycle updates rewrote the request time.
 *
 * This forward change keeps existing values and the required NOT NULL timestamp,
 * but removes the automatic ON UPDATE rewrite. It does not repair rows already drifted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('network_reservations')) {
            return;
        }

        // Explicit DEFAULT CURRENT_TIMESTAMP without ON UPDATE matches the project's
        // useCurrent() create pattern (see device_repairs.started_at) and stops drift.
        DB::statement('ALTER TABLE `network_reservations` MODIFY `requested_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    /**
     * Refuses to restore ON UPDATE CURRENT_TIMESTAMP, which silently corrupts request times.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Refusing to roll back: restoring ON UPDATE CURRENT_TIMESTAMP on network_reservations.requested_at would rewrite request times on later updates. Keep the forward fix or restore from backup.'
        );
    }
};
