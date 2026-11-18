<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Continuity marker: which declaration currently explains a known quantity pool.
     *
     * Cleared on empty/unknown transitions and when inbound receipts rebuild or mix the pool.
     * Survives proportional known depletion so sales can cite the declaration without matching
     * coincident totals from a later receipt-only pool.
     */
    public function up(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->foreignId('active_cost_declaration_id')
                ->nullable()
                ->after('cost_total')
                ->constrained('inventory_cost_declarations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_cost_declaration_id');
        });
    }
};
