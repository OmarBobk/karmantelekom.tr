<?php

use App\Services\Pos\PosRollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The source rules of every cash movement before cashbox transfers, without the movement amount.
     */
    private const SOURCES = "(direction = 'in' and cash_intake_id is not null and cash_payout_id is null and expense_id is null and supplier_cash_payout_id is null and supplier_payment_id is null and ((type = 'sale_cash' and sale_payment_id is not null and customer_payment_id is null) or (type = 'customer_payment_cash' and customer_payment_id is not null and sale_payment_id is null))) or (direction = 'out' and type = 'expense_cash' and cash_payout_id is not null and expense_id is not null and cash_intake_id is null and sale_payment_id is null and customer_payment_id is null and supplier_cash_payout_id is null and supplier_payment_id is null) or (direction = 'out' and type = 'supplier_payment_cash' and supplier_cash_payout_id is not null and supplier_payment_id is not null and cash_intake_id is null and sale_payment_id is null and customer_payment_id is null and cash_payout_id is null and expense_id is null)";

    /**
     * Run the migrations.
     *
     * Owner deposits into and withdrawals from the drawer, in TRY. A transfer is persisted as
     * pending on the open session before the owner moves any cash, and claims its form's request
     * key. Confirming posts one deposit or withdrawal movement linked to it; these movements are
     * internal cashbox transfers, neither revenue nor expense.
     */
    public function up(): void
    {
        Schema::create('cashbox_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashbox_session_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->decimal('amount', 15, 2);
            $table->string('reason', 500);
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
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('cashbox_transfer_id')->nullable()->unique()->after('supplier_payment_id')->constrained()->restrictOnDelete();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table cash_movements drop constraint cash_movements_source_check');
            DB::statement('alter table cash_movements add constraint cash_movements_source_check check (amount > 0 and ((cashbox_transfer_id is null and ('.self::SOURCES.")) or (cashbox_transfer_id is not null and ((type = 'cash_deposit' and direction = 'in') or (type = 'cash_withdrawal' and direction = 'out')) and cash_intake_id is null and sale_payment_id is null and customer_payment_id is null and cash_payout_id is null and expense_id is null and supplier_cash_payout_id is null and supplier_payment_id is null)))");
            DB::statement("alter table cashbox_transfers add constraint cashbox_transfers_state_check check (amount > 0 and kind in ('deposit', 'withdrawal') and ((status = 'pending' and completed_at is null and released_at is null) or (status = 'completed' and completed_at is not null and released_at is null) or (status = 'released' and completed_at is null and released_by is not null and released_at is not null and resolution is not null and release_note is not null)))");
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
            DB::statement('alter table cash_movements add constraint cash_movements_source_check check (amount > 0 and ('.self::SOURCES.'))');
        }

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashbox_transfer_id');
        });

        Schema::dropIfExists('cashbox_transfers');
    }
};
