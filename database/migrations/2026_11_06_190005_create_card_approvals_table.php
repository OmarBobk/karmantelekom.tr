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
     * An external terminal approval recorded on a checkout attempt, independently of the sale.
     * One approval per attempt, bound to the terminal payment start it answers, and each
     * reference is unique within its terminal, so the same approval can never back two attempts
     * or two sales.
     */
    public function up(): void
    {
        $exactCollation = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true) ? 'utf8mb4_bin' : null;

        Schema::create('card_approvals', function (Blueprint $table) use ($exactCollation) {
            $table->id();
            $table->foreignId('checkout_attempt_id')->unique()->constrained()->restrictOnDelete();
            $table->char('terminal_payment_key', 36);
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_terminal_id')->constrained()->restrictOnDelete();
            $table->string('reference', 40)->collation($exactCollation);
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2);
            $table->string('status', 16);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
            $table->string('void_reference', 40)->nullable();
            $table->text('void_note')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->unique(['card_terminal_id', 'reference']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table card_approvals add constraint card_approvals_amount_check check (amount > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        Schema::dropIfExists('card_approvals');
    }
};
