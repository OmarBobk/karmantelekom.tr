<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Immutable before/after history of identifier typo corrections. The old value is
     * released for reuse but stays searchable here.
     */
    public function up(): void
    {
        $exactCollation = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            ? 'utf8mb4_bin'
            : null;

        Schema::create('inventory_unit_identifier_corrections', function (Blueprint $table) use ($exactCollation) {
            $table->id();
            $table->foreignId('inventory_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_unit_identifier_id')->constrained(indexName: 'identifier_corrections_identifier_id_foreign')->restrictOnDelete();
            $table->string('type', 16);
            $table->unsignedTinyInteger('position');
            $table->string('old_value', 64)->collation($exactCollation);
            $table->string('new_value', 64)->collation($exactCollation);
            $table->text('reason');
            $table->foreignId('inventory_movement_id')
                ->unique('identifier_corrections_movement_id_unique')
                ->constrained(indexName: 'identifier_corrections_movement_id_foreign')
                ->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('corrected_at')->useCurrent();
            $table->timestamp('created_at')->nullable();

            $table->index(['type', 'old_value'], 'inventory_unit_identifier_corrections_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_unit_identifier_corrections');
    }
};
