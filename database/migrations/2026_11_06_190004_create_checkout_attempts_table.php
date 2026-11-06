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
     * the payload freezes once a card approval is recorded. Failure fields describe the last
     * confirmed finalization failure; the attempt stays open and recoverable.
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
