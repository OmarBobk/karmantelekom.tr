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
     * A customer payment received later against open debts of one store customer, and its
     * allocations to those debts, oldest sale first. Both are append-only; the debt and sale stay
     * unchanged, and a debt's outstanding balance is its original amount less its allocations.
     * Cash payments get a gapless receipt number per store; card and bank transfer payments carry
     * the external reference, unique per store and method.
     */
    public function up(): void
    {
        Schema::create('customer_payment_number_sequences', function (Blueprint $table) {
            $table->foreignId('store_id')->primary()->constrained()->restrictOnDelete();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2);
            $table->string('method', 16);
            $table->string('receipt_number', 32)->nullable()->unique();
            $table->string('reference', 64)->nullable();
            $table->string('reference_key', 64)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->char('request_fingerprint', 64);
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('created_at')->nullable();

            $table->unique(['store_id', 'method', 'reference_key']);
            $table->index(['store_id', 'customer_id', 'received_at']);
        });

        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_debt_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamp('created_at')->nullable();

            $table->unique(['customer_payment_id', 'customer_debt_id'], 'customer_payment_allocations_payment_debt_unique');
            $table->index(['customer_debt_id']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("alter table customer_payments add constraint customer_payments_method_check check (amount > 0 and ((method = 'cash' and receipt_number is not null and reference is null and reference_key is null) or (method in ('card', 'bank_transfer') and receipt_number is null and reference is not null and reference_key is not null)))");
            DB::statement('alter table customer_payment_allocations add constraint customer_payment_allocations_amount_check check (amount > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        Schema::dropIfExists('customer_payment_allocations');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('customer_payment_number_sequences');
    }
};
