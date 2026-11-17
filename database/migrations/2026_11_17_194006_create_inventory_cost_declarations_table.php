<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only owner declarations of the total TRY recorded cost for current on-hand quantity.
     *
     * Cost-only facts: they do not create stock movements. Idempotency prevents duplicate submits.
     */
    public function up(): void
    {
        Schema::create('inventory_cost_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->char('currency', 3);
            $table->decimal('declared_total', 15, 2);
            $table->string('prior_cost_status', 16);
            $table->char('prior_cost_currency', 3)->nullable();
            $table->decimal('prior_cost_total', 15, 2)->nullable();
            $table->string('resulting_cost_status', 16);
            $table->char('resulting_cost_currency', 3);
            $table->decimal('resulting_cost_total', 15, 2);
            $table->text('reason');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->char('request_fingerprint', 64);
            $table->timestamp('declared_at')->useCurrent();
            $table->timestamp('created_at')->nullable();

            $table->index(['store_id', 'product_definition_id', 'declared_at'], 'inventory_cost_declarations_history_index');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("alter table inventory_cost_declarations add constraint inventory_cost_declarations_amounts_check check (quantity > 0 and declared_total >= 0 and currency = 'TRY')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_declarations');
    }
};
