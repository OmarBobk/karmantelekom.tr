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
     * Cashbox sessions per store (at most one open, enforced by open_store_id being unique and
     * set only while open), cash intakes that bind cash about to be accepted to the open session
     * before staff are told to take it, and the append-only cash movements posted when a sale or
     * customer payment completes. Tracking starts with each store's first session; earlier sales
     * and payments get no movements.
     */
    public function up(): void
    {
        Schema::create('cashbox_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('open_store_id')->nullable()->unique();
            $table->string('status', 16);
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->decimal('opening_expected', 15, 2)->nullable();
            $table->decimal('opening_counted', 15, 2);
            $table->decimal('opening_difference', 15, 2)->nullable();
            $table->string('opening_note', 500)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_closing', 15, 2)->nullable();
            $table->decimal('counted_closing', 15, 2)->nullable();
            $table->decimal('closing_difference', 15, 2)->nullable();
            $table->string('closing_note', 500)->nullable();
            $table->timestamps();

            $table->index(['store_id', 'opened_at']);
        });

        Schema::create('cash_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashbox_session_id')->constrained()->restrictOnDelete();
            $table->string('source_type', 32);
            $table->foreignId('checkout_attempt_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('status', 16);
            $table->string('pending_source', 64)->nullable()->unique();
            $table->string('request_key', 64)->unique();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->string('release_note', 500)->nullable();
            $table->timestamps();

            $table->index(['cashbox_session_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashbox_session_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->decimal('amount', 15, 2);
            $table->foreignId('cash_intake_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('sale_payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->nullable();

            $table->index(['cashbox_session_id', 'occurred_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("alter table cashbox_sessions add constraint cashbox_sessions_state_check check (opening_counted >= 0 and ((status = 'open' and open_store_id = store_id and closed_at is null and counted_closing is null) or (status = 'closed' and open_store_id is null and closed_at is not null and closed_by is not null and expected_closing is not null and counted_closing >= 0 and closing_difference = counted_closing - expected_closing)))");
            DB::statement("alter table cash_intakes add constraint cash_intakes_state_check check (amount > 0 and ((source_type = 'sale' and checkout_attempt_id is not null and customer_id is null) or (source_type = 'customer_payment' and customer_id is not null and checkout_attempt_id is null)) and ((status = 'pending' and pending_source is not null) or (status = 'completed' and pending_source is null and completed_at is not null) or (status = 'released' and pending_source is null and released_by is not null and release_note is not null)))");
            DB::statement("alter table cash_movements add constraint cash_movements_source_check check (amount > 0 and cash_intake_id is not null and ((type = 'sale_cash' and sale_payment_id is not null and customer_payment_id is null) or (type = 'customer_payment_cash' and customer_payment_id is not null and sale_payment_id is null)))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_intakes');
        Schema::dropIfExists('cashbox_sessions');
    }
};
