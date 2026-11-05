<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Exact serial lookup across product definitions cannot use the identity index,
     * whose second column is the per-definition scope.
     */
    public function up(): void
    {
        Schema::table('inventory_unit_identifiers', function (Blueprint $table) {
            $table->index(['type', 'value'], 'inventory_unit_identifiers_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_unit_identifiers', function (Blueprint $table) {
            $table->dropIndex('inventory_unit_identifiers_lookup_index');
        });
    }
};
