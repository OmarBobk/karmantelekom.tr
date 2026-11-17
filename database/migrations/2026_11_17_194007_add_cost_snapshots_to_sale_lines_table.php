<?php

use App\Services\Pos\PosRollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable sale-time recorded-cost snapshots. Null means a sale completed before costing.
     */
    public function up(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            $table->string('cost_status', 32)->nullable()->after('discounted_by');
            $table->char('cost_currency', 3)->nullable()->after('cost_status');
            $table->decimal('cost_amount', 15, 2)->nullable()->after('cost_currency');
            $table->string('cost_basis', 32)->nullable()->after('cost_amount');
            $table->string('cost_basis_version', 32)->nullable()->after('cost_basis');
            $table->string('cost_unknown_reason', 64)->nullable()->after('cost_basis_version');
            $table->string('cost_exclusion_reason', 64)->nullable()->after('cost_unknown_reason');
            $table->unsignedInteger('pool_quantity_before')->nullable()->after('cost_exclusion_reason');
            $table->decimal('pool_cost_total_before', 15, 2)->nullable()->after('pool_quantity_before');
            $table->foreignId('cost_declaration_id')->nullable()->after('pool_cost_total_before')->constrained('inventory_cost_declarations')->restrictOnDelete();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("alter table sale_lines add constraint sale_lines_cost_status_check check (
                cost_status is null
                or cost_status in ('known', 'unknown', 'excluded_network')
            )");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table sale_lines drop check sale_lines_cost_status_check');
        }

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_declaration_id');
            $table->dropColumn([
                'cost_status',
                'cost_currency',
                'cost_amount',
                'cost_basis',
                'cost_basis_version',
                'cost_unknown_reason',
                'cost_exclusion_reason',
                'pool_quantity_before',
                'pool_cost_total_before',
            ]);
        });
    }
};
