<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The revision advances on every lifecycle or condition change, so a form opened
     * before another operator's change is rejected even if the status has returned.
     */
    public function up(): void
    {
        Schema::table('inventory_units', function (Blueprint $table) {
            $table->unsignedInteger('lifecycle_revision')->default(0)->after('condition');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_units', function (Blueprint $table) {
            $table->dropColumn('lifecycle_revision');
        });
    }
};
