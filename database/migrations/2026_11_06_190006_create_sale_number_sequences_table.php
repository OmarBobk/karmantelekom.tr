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
     * The last issued sale number per store. Incremented under a row lock inside the sale
     * transaction, so numbers are gapless: a rolled-back sale releases its number.
     */
    public function up(): void
    {
        Schema::create('sale_number_sequences', function (Blueprint $table) {
            $table->foreignId('store_id')->primary()->constrained()->restrictOnDelete();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        Schema::dropIfExists('sale_number_sequences');
    }
};
