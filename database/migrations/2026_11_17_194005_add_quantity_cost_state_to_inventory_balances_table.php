<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quantity-pool recorded-cost state for weighted-average costing.
     *
     * Existing nonzero balances start unknown (no guessed historical average). Zero balances
     * start empty so the next compatible TRY receipt can open a known pool.
     */
    public function up(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->string('cost_status', 16)->default('empty')->after('quantity');
            $table->char('cost_currency', 3)->nullable()->after('cost_status');
            $table->decimal('cost_total', 15, 2)->nullable()->after('cost_currency');
        });

        DB::table('inventory_balances')->where('quantity', '>', 0)->update([
            'cost_status' => 'unknown',
            'cost_currency' => null,
            'cost_total' => null,
        ]);

        DB::table('inventory_balances')->where('quantity', 0)->update([
            'cost_status' => 'empty',
            'cost_currency' => null,
            'cost_total' => null,
        ]);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("alter table inventory_balances add constraint inventory_balances_cost_status_check check (cost_status in ('empty', 'known', 'unknown'))");
            DB::statement("alter table inventory_balances add constraint inventory_balances_cost_state_check check (
                (quantity = 0 and cost_status = 'empty' and cost_currency is null and cost_total is null)
                or (quantity > 0 and cost_status = 'unknown' and cost_currency is null and cost_total is null)
                or (quantity > 0 and cost_status = 'known' and cost_currency is not null and cost_total is not null and cost_total >= 0)
            )");
            DB::statement("alter table inventory_balances add constraint inventory_balances_cost_currency_check check (cost_currency is null or cost_currency in ('TRY', 'USD'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table inventory_balances drop check inventory_balances_cost_currency_check');
            DB::statement('alter table inventory_balances drop check inventory_balances_cost_state_check');
            DB::statement('alter table inventory_balances drop check inventory_balances_cost_status_check');
        }

        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->dropColumn(['cost_status', 'cost_currency', 'cost_total']);
        });
    }
};
