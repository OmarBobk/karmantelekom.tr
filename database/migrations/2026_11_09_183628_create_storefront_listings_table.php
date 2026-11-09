<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The explicit link between an operational product and its legacy storefront product, with
     * publication per channel. The legacy product keeps its URLs, media, translations, prices,
     * and order references; deleting it in the legacy admin removes only the link.
     */
    public function up(): void
    {
        Schema::create('storefront_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_definition_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('linked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('retail_published_at')->nullable();
            $table->timestamp('wholesale_published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Refuses to drop recorded links; fix forward with a new migration instead.
     */
    public function down(): void
    {
        if (Schema::hasTable('storefront_listings') && DB::table('storefront_listings')->exists()) {
            throw new RuntimeException('Phase 7.5 rollback refused: storefront listings exist. Nothing was changed. Fix forward with a new migration instead of rolling back.');
        }

        Schema::dropIfExists('storefront_listings');
    }
};
