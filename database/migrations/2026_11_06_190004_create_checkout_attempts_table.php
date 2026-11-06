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
     * A reviewed POS cart persisted before the cashier takes payment. The key is stable for
     * the attempt's whole life; the revision advances whenever the reviewed cart changes and
     * the payload freezes once the cashier starts a terminal payment, before the card is charged.
     * The terminal payment fields hold the current start: its own key (new for every start, never
     * reused), the client request key that makes a retried start a replay, and the terminal and
     * amount the cashier was told to charge. They stay set once an approval is recorded and are
     * cleared only by an audited owner resolution, which keeps the cleared start's key so a
     * retried resolution replays. Failure fields describe the last confirmed finalization
     * failure; the attempt stays open and recoverable.
     */
    public function up(): void
    {
        Schema::create('checkout_attempts', function (Blueprint $table) {
            $table->id();
            $table->char('key', 36)->unique();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 16);
            $table->unsignedInteger('revision')->default(1);
            $table->char('currency', 3);
            $table->json('lines');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_total', 15, 2);
            $table->decimal('total', 15, 2);
            $table->char('payload_hash', 64);
            $table->timestamp('frozen_at')->nullable();
            $table->char('terminal_payment_key', 36)->nullable()->unique();
            $table->char('terminal_payment_request_key', 36)->nullable();
            $table->char('terminal_payment_cleared_key', 36)->nullable();
            $table->foreignId('terminal_payment_terminal_id')->nullable()->constrained('card_terminals')->restrictOnDelete();
            $table->decimal('terminal_payment_amount', 15, 2)->nullable();
            $table->foreignId('terminal_payment_started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('terminal_payment_started_at')->nullable();
            $table->char('finalize_fingerprint', 64)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table checkout_attempts add constraint checkout_attempts_total_check check (total > 0 and subtotal > 0 and discount_total >= 0 and total = subtotal - discount_total)');
            DB::statement('alter table checkout_attempts add constraint checkout_attempts_terminal_payment_check check ((terminal_payment_started_at is null and terminal_payment_key is null and terminal_payment_request_key is null and terminal_payment_terminal_id is null and terminal_payment_amount is null and terminal_payment_started_by is null) or (terminal_payment_started_at is not null and terminal_payment_key is not null and terminal_payment_request_key is not null and terminal_payment_terminal_id is not null and terminal_payment_started_by is not null and frozen_at is not null and terminal_payment_amount > 0 and terminal_payment_amount <= total))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        Schema::dropIfExists('checkout_attempts');
    }
};
