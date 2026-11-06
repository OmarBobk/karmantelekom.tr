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
     * The individual TRY selling price of a used device. NULL means not priced; there is no
     * fallback to the definition price.
     */
    public function up(): void
    {
        Schema::table('inventory_units', function (Blueprint $table) {
            $table->decimal('selling_price', 15, 2)->nullable()->after('lifecycle_revision');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table inventory_units add constraint inventory_units_selling_price_check check (selling_price is null or selling_price > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table inventory_units drop constraint inventory_units_selling_price_check');
        }

        Schema::table('inventory_units', function (Blueprint $table) {
            $table->dropColumn('selling_price');
        });
    }
};
