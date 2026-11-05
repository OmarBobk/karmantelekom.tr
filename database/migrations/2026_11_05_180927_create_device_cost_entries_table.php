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
     * Attributable device costs, one fact per row. A NULL amount (with NULL currency) means
     * the amount is unknown; 0.00 is a confirmed zero. Amounts are never edited: a correction
     * reverses the entry and may record a replacement that points back at it.
     * recorded_at has an explicit default so MariaDB does not add ON UPDATE CURRENT_TIMESTAMP
     * to it and overwrite the recorded time when the entry is reversed.
     */
    public function up(): void
    {
        Schema::create('device_cost_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('device_repair_id')->nullable();
            $table->string('category', 24);
            $table->string('description', 255)->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->foreignId('inventory_movement_id')->constrained()->restrictOnDelete();
            $table->foreignId('replaces_entry_id')->nullable()->unique()->constrained('device_cost_entries')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->foreignId('reversal_movement_id')->nullable()->constrained('inventory_movements')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->foreign(['device_repair_id', 'inventory_unit_id'], 'device_cost_entries_repair_unit_foreign')
                ->references(['id', 'inventory_unit_id'])
                ->on('device_repairs')
                ->restrictOnDelete();
            $table->index(['inventory_unit_id', 'recorded_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table device_cost_entries add constraint device_cost_entries_amount_check check ((amount is null and currency is null) or (amount is not null and amount >= 0 and currency is not null))');
            DB::statement('alter table device_cost_entries add constraint device_cost_entries_reversal_check check ((reversed_at is null and reversed_by is null and reversal_movement_id is null) or (reversed_at is not null and reversed_by is not null and reversal_movement_id is not null))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_cost_entries');
    }
};
