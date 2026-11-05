<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->string('reason', 32);
            $table->text('note')->nullable();
            $table->integer('quantity_change');
            $table->unsignedInteger('quantity_after')->nullable();
            $table->string('unit_status_before', 16)->nullable();
            $table->string('unit_status_after', 16)->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->char('request_fingerprint', 64);
            $table->string('opening_balance_scope', 64)
                ->nullable()
                ->storedAs("case when `type` = 'opening_balance' and `inventory_unit_id` is null then concat(`store_id`, ':', `product_definition_id`) end")
                ->unique();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['store_id', 'product_definition_id', 'occurred_at'], 'inventory_movements_history_index');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table inventory_movements add constraint inventory_movements_quantity_change_check check (quantity_change <> 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
