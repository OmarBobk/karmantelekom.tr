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
     * A completed POS sale. Append-only: corrections, returns, and refunds will be separate
     * records. Each checkout attempt completes at most one sale.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('checkout_attempt_id')->unique()->constrained()->restrictOnDelete();
            $table->string('number', 32)->unique();
            $table->unsignedInteger('sequence');
            $table->char('currency', 3);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_total', 15, 2);
            $table->decimal('total', 15, 2);
            $table->decimal('cash_tendered', 15, 2)->nullable();
            $table->decimal('change_given', 15, 2)->default(0);
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamp('created_at')->nullable();

            $table->unique(['store_id', 'sequence']);
            $table->index(['store_id', 'completed_at']);
            $table->index(['store_id', 'user_id', 'completed_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table sales add constraint sales_total_check check (total > 0 and discount_total >= 0 and total = subtotal - discount_total and change_given >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        Schema::dropIfExists('sales');
    }
};
