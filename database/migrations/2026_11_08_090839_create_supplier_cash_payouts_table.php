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
     * TRY cash paid to a supplier from a tracked cashbox. A supplier cash payout reserves the amount
     * on the open session and against the invoice before staff hand it out, and claims the payment
     * form's request key. Confirming it records the supplier payment, its ledger entry, and one
     * outgoing cash movement linked to both.
     */
    public function up(): void
    {
        Schema::create('supplier_cash_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashbox_session_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('paid_on');
            $table->string('reference', 64);
            $table->string('reference_key', 64);
            $table->string('note', 1000)->nullable();
            $table->string('request_key', 64)->unique();
            $table->char('request_fingerprint', 64);
            $table->string('status', 16);
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->string('resolution', 32)->nullable();
            $table->string('release_note', 500)->nullable();
            $table->timestamps();

            $table->index(['cashbox_session_id', 'status']);
            $table->index(['purchase_id', 'status']);
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->foreignId('supplier_cash_payout_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('supplier_cash_payout_id')->nullable()->unique()->after('expense_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_payment_id')->nullable()->unique()->after('supplier_cash_payout_id')->constrained()->restrictOnDelete();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table cash_movements drop constraint cash_movements_source_check');
            DB::statement("alter table cash_movements add constraint cash_movements_source_check check (amount > 0 and ((direction = 'in' and cash_intake_id is not null and cash_payout_id is null and expense_id is null and supplier_cash_payout_id is null and supplier_payment_id is null and ((type = 'sale_cash' and sale_payment_id is not null and customer_payment_id is null) or (type = 'customer_payment_cash' and customer_payment_id is not null and sale_payment_id is null))) or (direction = 'out' and type = 'expense_cash' and cash_payout_id is not null and expense_id is not null and cash_intake_id is null and sale_payment_id is null and customer_payment_id is null and supplier_cash_payout_id is null and supplier_payment_id is null) or (direction = 'out' and type = 'supplier_payment_cash' and supplier_cash_payout_id is not null and supplier_payment_id is not null and cash_intake_id is null and sale_payment_id is null and customer_payment_id is null and cash_payout_id is null and expense_id is null)))");
            DB::statement("alter table supplier_cash_payouts add constraint supplier_cash_payouts_state_check check (amount > 0 and ((status = 'pending' and completed_at is null and released_at is null) or (status = 'completed' and completed_at is not null and released_at is null) or (status = 'released' and completed_at is null and released_by is not null and released_at is not null and resolution is not null and release_note is not null)))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table cash_movements drop constraint cash_movements_source_check');
            DB::statement("alter table cash_movements add constraint cash_movements_source_check check (amount > 0 and ((direction = 'in' and cash_intake_id is not null and cash_payout_id is null and expense_id is null and ((type = 'sale_cash' and sale_payment_id is not null and customer_payment_id is null) or (type = 'customer_payment_cash' and customer_payment_id is not null and sale_payment_id is null))) or (direction = 'out' and type = 'expense_cash' and cash_payout_id is not null and expense_id is not null and cash_intake_id is null and sale_payment_id is null and customer_payment_id is null)))");
        }

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_payment_id');
            $table->dropConstrainedForeignId('supplier_cash_payout_id');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_cash_payout_id');
        });

        Schema::dropIfExists('supplier_cash_payouts');
    }
};
