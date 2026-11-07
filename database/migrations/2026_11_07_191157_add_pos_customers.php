<?php

use App\Exceptions\Pos\PosRollbackBlockedException;
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
     * Counter customers reuse the customers table. A counter customer belongs to one store and is
     * identified there by a normalized phone number. Rows created before this migration keep a
     * null store and stay outside POS lookup. A checkout attempt and its sale may name one
     * customer; a walk-in sale names none.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->string('phone_normalized', 16)->nullable()->after('phone');
            $table->foreignId('created_by')->nullable()->after('address')->constrained('users')->restrictOnDelete();
            $table->unique(['store_id', 'phone_normalized']);
            $table->index(['store_id', 'name']);
        });

        Schema::table('checkout_attempts', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->restrictOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->restrictOnDelete();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table customers add constraint customers_store_phone_check check (store_id is null or phone_normalized is not null)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        $storeCustomers = DB::table('customers')->whereNotNull('store_id')->count();

        if ($storeCustomers > 0) {
            throw new PosRollbackBlockedException(['customers (store)' => $storeCustomers]);
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table customers drop constraint customers_store_phone_check');
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('checkout_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropForeign(['created_by']);
            $table->dropUnique(['store_id', 'phone_normalized']);
            $table->dropIndex(['store_id', 'name']);
            $table->dropColumn(['store_id', 'phone_normalized', 'created_by']);
        });
    }
};
