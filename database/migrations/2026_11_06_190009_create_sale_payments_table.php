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
     * Final payment allocations of a completed sale; their amounts sum to the sale total.
     * Cash rows keep the tendered amount and change for later cashbox reconciliation. Card
     * rows reference the terminal approval they captured; an approval backs at most one
     * payment. No card numbers are ever stored.
     */
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('method', 16);
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2);
            $table->decimal('cash_tendered', 15, 2)->nullable();
            $table->decimal('change_given', 15, 2)->nullable();
            $table->foreignId('card_approval_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('card_terminal_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('terminal_reference', 40)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->nullable();

            $table->index(['store_id', 'method', 'occurred_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("alter table sale_payments add constraint sale_payments_method_check check ((method = 'cash' and amount > 0 and cash_tendered >= amount and change_given = cash_tendered - amount and card_approval_id is null) or (method = 'card' and amount > 0 and card_approval_id is not null and cash_tendered is null and change_given is null))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        Schema::dropIfExists('sale_payments');
    }
};
