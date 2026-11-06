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
     * The shared TRY selling price of quantity and serialized definitions. NULL means not
     * priced (not sellable); a set price is always positive. Used devices are priced per unit.
     */
    public function up(): void
    {
        Schema::table('product_definitions', function (Blueprint $table) {
            $table->decimal('selling_price', 15, 2)->nullable()->after('barcode');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table product_definitions add constraint product_definitions_selling_price_check check (selling_price is null or selling_price > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table product_definitions drop constraint product_definitions_selling_price_check');
        }

        Schema::table('product_definitions', function (Blueprint $table) {
            $table->dropColumn('selling_price');
        });
    }
};
