<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\PurchaseStatus;
use App\Enums\PurchaseTaxTreatment;
use App\Models\OperationalSupplier;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Empty draft invoices. Post them through PostPurchase so totals and ledger entries stay consistent.
 *
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'operational_supplier_id' => OperationalSupplier::factory(),
            'currency' => Currency::TRY,
            'tax_treatment' => PurchaseTaxTreatment::KDV_INCLUDED,
            'status' => PurchaseStatus::DRAFT,
            'invoice_date' => now()->toDateString(),
            'revision' => 1,
            'created_by' => User::factory(),
        ];
    }
}
