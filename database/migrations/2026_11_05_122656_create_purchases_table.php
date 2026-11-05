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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('operational_supplier_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->string('tax_treatment', 24);
            $table->string('status', 16);
            $table->string('supplier_invoice_number', 64)->nullable();
            $table->string('invoice_number_key', 64)->collation('utf8mb4_bin')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('items_total', 15, 2)->default(0);
            $table->decimal('additional_charges', 15, 2)->default(0);
            $table->string('additional_charges_note', 255)->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('correction_of_purchase_id')->nullable()->constrained('purchases')->restrictOnDelete();
            $table->foreignId('root_purchase_id')->nullable()->constrained('purchases')->restrictOnDelete();
            $table->string('supplier_name', 255)->nullable();
            $table->string('supplier_tax_number', 64)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('posted_invoice_scope', 128)
                ->collation('utf8mb4_bin')
                ->nullable()
                ->storedAs("case when `status` = 'posted' then concat(`operational_supplier_id`, ':', `invoice_number_key`) end")
                ->unique();
            $table->timestamps();

            $table->index(['store_id', 'status', 'id']);
            $table->index(['operational_supplier_id', 'currency', 'status']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table purchases add constraint purchases_amounts_check check (items_total >= 0 and additional_charges >= 0 and total = items_total + additional_charges and paid_total >= 0 and paid_total <= total)');
            DB::statement("alter table purchases add constraint purchases_currency_check check (currency in ('TRY', 'USD'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
