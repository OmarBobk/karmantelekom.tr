<?php

use App\Services\Inventory\DeviceLifecycleRollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only inspection results for used devices, one per inspection movement.
     */
    public function up(): void
    {
        Schema::create('device_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_movement_id')->unique()->constrained()->restrictOnDelete();
            $table->string('outcome', 16);
            $table->string('condition_before', 16);
            $table->string('condition_after', 16);
            $table->text('findings')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('inspected_at')->useCurrent();
            $table->timestamp('created_at')->nullable();

            $table->index(['inventory_unit_id', 'inspected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(DeviceLifecycleRollbackGuard::class)->assertNoRecordedLifecycleData();

        Schema::dropIfExists('device_inspections');
    }
};
