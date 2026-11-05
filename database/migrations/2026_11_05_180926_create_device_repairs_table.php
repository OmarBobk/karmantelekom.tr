<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A repair is opened once and closed once (completed or cancelled). The generated
     * open_scope column lets the database allow at most one open repair per unit.
     * started_at has an explicit default so MariaDB does not add ON UPDATE CURRENT_TIMESTAMP
     * to it and overwrite the start time when the repair is closed.
     */
    public function up(): void
    {
        Schema::create('device_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->string('status', 16);
            $table->string('repairer_type', 16);
            $table->string('repairer_name', 120)->nullable();
            $table->text('problem');
            $table->text('work_performed')->nullable();
            $table->text('close_reason')->nullable();
            $table->foreignId('started_movement_id')->unique()->constrained('inventory_movements')->restrictOnDelete();
            $table->foreignId('closed_movement_id')->nullable()->unique()->constrained('inventory_movements')->restrictOnDelete();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('open_scope')
                ->nullable()
                ->storedAs("case when `status` = 'open' then `inventory_unit_id` end")
                ->unique();
            $table->timestamps();

            $table->unique(['id', 'inventory_unit_id']);
            $table->index(['inventory_unit_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_repairs');
    }
};
