<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('product_definition_id')->constrained()->restrictOnDelete();
            $table->string('tracking_mode', 16);
            $table->string('product_name', 255);
            $table->string('product_model', 255)->nullable();
            $table->string('product_sku', 64)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();

            $table->unique(['purchase_id', 'line_number']);
            $table->index('product_definition_id');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table purchase_lines add constraint purchase_lines_amounts_check check (quantity > 0 and unit_cost > 0 and line_total = quantity * unit_cost)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
    }
};
