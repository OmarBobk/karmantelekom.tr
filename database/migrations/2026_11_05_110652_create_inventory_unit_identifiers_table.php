<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $exactCollation = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            ? 'utf8mb4_bin'
            : null;

        Schema::create('inventory_unit_identifiers', function (Blueprint $table) use ($exactCollation) {
            $table->id();
            $table->unsignedBigInteger('inventory_unit_id');
            $table->unsignedBigInteger('product_definition_id');
            $table->string('type', 16);
            $table->unsignedTinyInteger('position')->default(1);
            $table->string('value', 64)->collation($exactCollation);
            $table->unsignedBigInteger('uniqueness_scope')
                ->storedAs("case when `type` = 'serial' then `product_definition_id` else 0 end");
            $table->timestamps();

            $table->unique(['type', 'uniqueness_scope', 'value'], 'inventory_unit_identifiers_identity_unique');
            $table->unique(['inventory_unit_id', 'type', 'position'], 'inventory_unit_identifiers_slot_unique');
            $table->foreign(['inventory_unit_id', 'product_definition_id'], 'inventory_unit_identifiers_unit_foreign')
                ->references(['id', 'product_definition_id'])
                ->on('inventory_units')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_unit_identifiers');
    }
};
