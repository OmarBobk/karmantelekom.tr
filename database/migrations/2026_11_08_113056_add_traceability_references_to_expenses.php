<?php

use App\Services\Pos\PosRollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Optional supplier and device references on expenses and on the cash payouts that become
     * them, for traceability only: they change no stock, cost basis, or supplier balance.
     */
    public function up(): void
    {
        foreach (['cash_payouts' => 'reference', 'expenses' => 'reference'] as $table => $after) {
            Schema::table($table, function (Blueprint $table) use ($after) {
                $table->foreignId('operational_supplier_id')->nullable()->after($after)->constrained()->restrictOnDelete();
                $table->foreignId('inventory_unit_id')->nullable()->after('operational_supplier_id')->constrained()->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        foreach (['expenses', 'cash_payouts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('inventory_unit_id');
                $table->dropConstrainedForeignId('operational_supplier_id');
            });
        }
    }
};
