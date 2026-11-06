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
     * External card terminals used at a store's counter. The merchant and terminal numbers
     * printed on every slip define the scope in which an approval reference is unique.
     */
    public function up(): void
    {
        $exactCollation = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true) ? 'utf8mb4_bin' : null;

        Schema::create('card_terminals', function (Blueprint $table) use ($exactCollation) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->string('label', 100);
            $table->string('merchant_number', 32)->collation($exactCollation);
            $table->string('terminal_number', 32)->collation($exactCollation);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['merchant_number', 'terminal_number']);
            $table->index(['store_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        Schema::dropIfExists('card_terminals');
    }
};
