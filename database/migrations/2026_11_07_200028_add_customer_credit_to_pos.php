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
     * A reviewed checkout may leave part or all of its total unpaid for a store customer. The
     * attempt keeps the exact TRY credit amount and who authorized it; existing attempts are
     * fully paid (credit 0). A completed sale's payments plus its debt's original amount equal
     * the sale total. Each credited sale has exactly one debt row recording the original
     * obligation; debt payments and corrections will be separate records.
     */
    public function up(): void
    {
        Schema::table('checkout_attempts', function (Blueprint $table) {
            $table->decimal('credit_amount', 15, 2)->default(0)->after('total');
            $table->foreignId('credit_authorized_by')->nullable()->after('credit_amount')->constrained('users')->restrictOnDelete();
        });

        Schema::create('customer_debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->unique()->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->decimal('original_amount', 15, 2);
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->nullable();

            $table->index(['store_id', 'customer_id', 'occurred_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table checkout_attempts add constraint checkout_attempts_credit_check check (credit_amount >= 0 and credit_amount <= total and ((credit_amount = 0 and credit_authorized_by is null) or (credit_amount > 0 and customer_id is not null and credit_authorized_by is not null)))');
            DB::statement('alter table customer_debts add constraint customer_debts_amount_check check (original_amount > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        Schema::dropIfExists('customer_debts');

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table checkout_attempts drop constraint checkout_attempts_credit_check');
        }

        Schema::table('checkout_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_authorized_by');
            $table->dropColumn('credit_amount');
        });
    }
};
