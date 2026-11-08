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
     * General branch expenses in TRY, and cash payouts that reserve an expense's cash on the open
     * cashbox session before staff hand it out. Confirming a payout records its expense and one
     * outgoing cash movement; card and bank transfer expenses post no movement.
     *
     * Cash movements gain an explicit direction. Existing rows are all cash received, so they are
     * backfilled as 'in'; the default is then dropped so every new movement states its direction.
     */
    public function up(): void
    {
        Schema::create('cash_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashbox_session_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('category', 32);
            $table->string('description', 500);
            $table->date('expense_date');
            $table->string('reference', 100)->nullable();
            $table->string('status', 16);
            $table->string('request_key', 64)->unique();
            $table->char('request_fingerprint', 64);
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->string('resolution', 32)->nullable();
            $table->string('release_note', 500)->nullable();
            $table->timestamps();

            $table->index(['cashbox_session_id', 'status']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->string('category', 32);
            $table->string('description', 500);
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->string('method', 16);
            $table->string('reference', 100)->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_payout_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->char('request_fingerprint', 64)->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['store_id', 'expense_date', 'id']);
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->string('direction', 8)->default('in')->after('type');
            $table->foreignId('cash_payout_id')->nullable()->unique()->after('customer_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_id')->nullable()->unique()->after('cash_payout_id')->constrained()->restrictOnDelete();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table cash_movements alter column direction drop default');
            DB::statement('alter table cash_movements drop constraint cash_movements_source_check');
            DB::statement("alter table cash_movements add constraint cash_movements_source_check check (amount > 0 and ((direction = 'in' and cash_intake_id is not null and cash_payout_id is null and expense_id is null and ((type = 'sale_cash' and sale_payment_id is not null and customer_payment_id is null) or (type = 'customer_payment_cash' and customer_payment_id is not null and sale_payment_id is null))) or (direction = 'out' and type = 'expense_cash' and cash_payout_id is not null and expense_id is not null and cash_intake_id is null and sale_payment_id is null and customer_payment_id is null)))");
            DB::statement("alter table cash_payouts add constraint cash_payouts_state_check check (amount > 0 and ((status = 'pending' and completed_at is null and released_at is null) or (status = 'completed' and completed_at is not null and released_at is null) or (status = 'released' and completed_at is null and released_by is not null and released_at is not null and resolution is not null and release_note is not null)))");
            DB::statement("alter table expenses add constraint expenses_state_check check (amount > 0 and currency = 'TRY' and ((method = 'cash' and cash_payout_id is not null and idempotency_key is null) or (method in ('card', 'bank_transfer') and cash_payout_id is null and idempotency_key is not null and request_fingerprint is not null)))");
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
            DB::statement("alter table cash_movements add constraint cash_movements_source_check check (amount > 0 and cash_intake_id is not null and ((type = 'sale_cash' and sale_payment_id is not null and customer_payment_id is null) or (type = 'customer_payment_cash' and customer_payment_id is not null and sale_payment_id is null)))");
        }

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
            $table->dropConstrainedForeignId('cash_payout_id');
            $table->dropColumn('direction');
        });

        Schema::dropIfExists('expenses');
        Schema::dropIfExists('cash_payouts');
    }
};
