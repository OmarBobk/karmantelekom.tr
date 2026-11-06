<?php

use App\Services\Pos\PosRollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Gives every terminal payment start its own identity: the start's key (new for every
     * start, never reused), the client request key that makes a retried start a replay, and
     * the key of the start an owner resolution cleared so a retried resolution replays. Card
     * approvals record the start they answer.
     *
     * Installed attempts keep their history: a start that is still set gets a fresh key and
     * request key, its approval is bound to that key, and approvals whose start was already
     * cleared get their own key. A legacy request retried after this upgrade starts nothing
     * new, because the attempt is already frozen; it is refused like any other second start.
     */
    public function up(): void
    {
        $checksSupported = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        Schema::table('checkout_attempts', function (Blueprint $table) {
            $table->char('terminal_payment_key', 36)->nullable()->after('frozen_at');
            $table->char('terminal_payment_request_key', 36)->nullable()->after('terminal_payment_key');
            $table->char('terminal_payment_cleared_key', 36)->nullable()->after('terminal_payment_request_key');
        });

        Schema::table('card_approvals', function (Blueprint $table) {
            $table->char('terminal_payment_key', 36)->nullable()->after('checkout_attempt_id');
        });

        DB::table('checkout_attempts')->whereNotNull('terminal_payment_started_at')->orderBy('id')->each(function (object $attempt): void {
            DB::table('checkout_attempts')->where('id', $attempt->id)->update([
                'terminal_payment_key' => (string) Str::uuid(),
                'terminal_payment_request_key' => (string) Str::uuid(),
            ]);
        });

        DB::table('card_approvals')->orderBy('id')->each(function (object $approval): void {
            $startKey = DB::table('checkout_attempts')->where('id', $approval->checkout_attempt_id)->value('terminal_payment_key');

            DB::table('card_approvals')->where('id', $approval->id)->update(['terminal_payment_key' => $startKey ?? (string) Str::uuid()]);
        });

        Schema::table('checkout_attempts', function (Blueprint $table) {
            $table->unique('terminal_payment_key');
        });

        Schema::table('card_approvals', function (Blueprint $table) {
            $table->char('terminal_payment_key', 36)->nullable(false)->change();
        });

        if ($checksSupported) {
            DB::statement('alter table checkout_attempts drop constraint checkout_attempts_terminal_payment_check');
            DB::statement('alter table checkout_attempts add constraint checkout_attempts_terminal_payment_check check ((terminal_payment_started_at is null and terminal_payment_key is null and terminal_payment_request_key is null and terminal_payment_terminal_id is null and terminal_payment_amount is null and terminal_payment_started_by is null) or (terminal_payment_started_at is not null and terminal_payment_key is not null and terminal_payment_request_key is not null and terminal_payment_terminal_id is not null and terminal_payment_started_by is not null and frozen_at is not null and terminal_payment_amount > 0 and terminal_payment_amount <= total))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table checkout_attempts drop constraint checkout_attempts_terminal_payment_check');
            DB::statement('alter table checkout_attempts add constraint checkout_attempts_terminal_payment_check check ((terminal_payment_started_at is null and terminal_payment_terminal_id is null and terminal_payment_amount is null and terminal_payment_started_by is null) or (terminal_payment_started_at is not null and terminal_payment_terminal_id is not null and terminal_payment_started_by is not null and frozen_at is not null and terminal_payment_amount > 0 and terminal_payment_amount <= total))');
        }

        Schema::table('card_approvals', function (Blueprint $table) {
            $table->dropColumn('terminal_payment_key');
        });

        Schema::table('checkout_attempts', function (Blueprint $table) {
            $table->dropUnique(['terminal_payment_key']);
            $table->dropColumn(['terminal_payment_key', 'terminal_payment_request_key', 'terminal_payment_cleared_key']);
        });
    }
};
