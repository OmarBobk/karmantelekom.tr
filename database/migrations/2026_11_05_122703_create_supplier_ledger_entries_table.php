<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only supplier account. Positive amounts are owed to the supplier,
     * negative amounts settle or cancel an obligation. Each source fact posts once.
     */
    public function up(): void
    {
        Schema::create('supplier_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->string('type', 24);
            $table->decimal('amount', 15, 2);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('entry_scope', 64)
                ->storedAs("concat(`type`, ':', coalesce(`supplier_payment_id`, `purchase_id`))")
                ->unique();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['operational_supplier_id', 'currency', 'occurred_at', 'id'], 'supplier_ledger_statement_index');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table supplier_ledger_entries add constraint supplier_ledger_entries_amount_check check (amount <> 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_ledger_entries');
    }
};
