<?php

use App\Services\Inventory\ReconciliationRollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Opening stock is allowed only while a branch's initial inventory setup is open. Existing
     * branches start open (null) and keep all their history; an owner closes setup once.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->timestamp('inventory_setup_completed_at')->nullable();
            $table->foreignId('inventory_setup_completed_by')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations. Refused while any reconciliation data exists.
     */
    public function down(): void
    {
        app(ReconciliationRollbackGuard::class)->assertNoRecordedReconciliationData();

        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_setup_completed_by');
            $table->dropColumn('inventory_setup_completed_at');
        });
    }
};
