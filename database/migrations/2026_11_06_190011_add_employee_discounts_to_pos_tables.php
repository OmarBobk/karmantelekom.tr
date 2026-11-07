<?php

use App\Services\Pos\PosRollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Employee line discounts. An owner grants an employee a maximum discount in basis points
     * (1000 = 10%), and may give a used device a minimum selling price that replaces that
     * percentage for the device. Completed sale lines keep the per-unit discount, who gave it,
     * which limit applied and the lowest price it allowed.
     *
     * Installed sale lines are backfilled from what they already record: the per-unit discount
     * is list price minus unit price, and a discounted line was an owner override by the
     * sale's cashier, the only discount the earlier counter allowed.
     */
    public function up(): void
    {
        $checksSupported = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        Schema::create('pos_discount_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('max_discount_basis_points');
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('inventory_units', function (Blueprint $table) {
            $table->decimal('minimum_selling_price', 15, 2)->nullable()->after('selling_price');
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->decimal('unit_discount', 15, 2)->default(0)->after('unit_price');
            $table->string('discount_limit', 16)->nullable()->after('adjustment_reason');
            $table->unsignedSmallInteger('discount_basis_points')->nullable()->after('discount_limit');
            $table->decimal('discount_lowest_unit_price', 15, 2)->nullable()->after('discount_basis_points');
            $table->foreignId('discounted_by')->nullable()->after('discount_lowest_unit_price')->constrained('users')->restrictOnDelete();
        });

        DB::table('sale_lines')->update(['unit_discount' => DB::raw('list_unit_price - unit_price')]);

        DB::table('sale_lines')->whereColumn('unit_price', '<', 'list_unit_price')->orderBy('id')->each(function (object $line): void {
            DB::table('sale_lines')->where('id', $line->id)->update([
                'discount_limit' => 'owner',
                'discounted_by' => DB::table('sales')->where('id', $line->sale_id)->value('user_id'),
            ]);
        });

        if ($checksSupported) {
            DB::statement('alter table pos_discount_grants add constraint pos_discount_grants_basis_points_check check (max_discount_basis_points between 1 and 10000)');
            DB::statement('alter table inventory_units add constraint inventory_units_minimum_selling_price_check check (minimum_selling_price is null or (selling_price is not null and minimum_selling_price > 0 and minimum_selling_price <= selling_price))');
            DB::statement("alter table sale_lines add constraint sale_lines_discount_check check (unit_discount = list_unit_price - unit_price and ((unit_discount = 0 and discounted_by is null and discount_limit is null) or (unit_discount > 0 and discounted_by is not null and discount_limit in ('owner', 'percentage', 'device_minimum'))))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table sale_lines drop constraint sale_lines_discount_check');
            DB::statement('alter table inventory_units drop constraint inventory_units_minimum_selling_price_check');
        }

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discounted_by');
            $table->dropColumn(['unit_discount', 'discount_limit', 'discount_basis_points', 'discount_lowest_unit_price']);
        });

        Schema::table('inventory_units', function (Blueprint $table) {
            $table->dropColumn('minimum_selling_price');
        });

        Schema::dropIfExists('pos_discount_grants');
    }
};
