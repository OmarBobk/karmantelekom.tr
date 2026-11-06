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
     * One sold item with the commercial values reviewed at checkout. Product and device
     * details are snapshots, so later catalog edits never change a completed sale. The unit
     * reference is indexed but not unique: an approved future return may make a device
     * sellable again.
     */
    public function up(): void
    {
        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('product_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('tracking_mode', 32);
            $table->string('product_name');
            $table->string('product_model')->nullable();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('unit_identifier', 80)->nullable();
            $table->string('unit_condition', 16)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('list_unit_price', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->text('adjustment_reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['sale_id', 'line_number']);
            $table->index('inventory_unit_id');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table sale_lines add constraint sale_lines_amounts_check check (quantity > 0 and list_unit_price > 0 and unit_price > 0 and discount_amount >= 0 and line_total > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PosRollbackGuard::class)->assertNoRecordedPosData();

        Schema::dropIfExists('sale_lines');
    }
};
