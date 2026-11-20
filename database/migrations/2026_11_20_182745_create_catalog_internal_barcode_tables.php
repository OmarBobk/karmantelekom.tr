<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared internal barcode sequence (200001–299999) and create-submission replay keys.
     */
    public function up(): void
    {
        Schema::create('catalog_barcode_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('last_number');
            $table->timestamps();
        });

        $now = now();

        DB::table('catalog_barcode_sequences')->insert([
            'id' => 1,
            'last_number' => 200000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::create('product_definition_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('request_key', 64)->unique();
            $table->string('request_fingerprint', 64);
            $table->foreignId('product_definition_id')->constrained()->restrictOnDelete();
            $table->timestamp('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_definition_submissions');
        Schema::dropIfExists('catalog_barcode_sequences');
    }
};
