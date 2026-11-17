<?php

use App\Services\Pos\PosRollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frozen device/acquisition cost evidence captured at sale completion.
     *
     * Amounts and inclusion decisions are snapshotted so later reversals cannot rewrite history.
     */
    public function up(): void
    {
        Schema::create('sale_line_cost_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_line_id')->constrained()->restrictOnDelete();
            $table->string('fact_type', 32);
            $table->string('inclusion', 24);
            $table->string('category', 32)->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->foreignId('purchase_receipt_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('device_cost_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('entry_reversed_at_sale')->default(false);
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['sale_line_id', 'id']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("alter table sale_line_cost_facts add constraint sale_line_cost_facts_inclusion_check check (inclusion in ('included', 'excluded', 'unknown'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        Schema::dropIfExists('sale_line_cost_facts');
    }
};
