<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Purchasing\CreatePurchaseDraft;
use App\Actions\Purchasing\PostPurchase;
use App\Actions\Purchasing\SavePurchaseDraft;
use App\Enums\Currency;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\User;

/**
 * Builds the acceptance walkthrough invoice through the real actions.
 */
trait InteractsWithPurchasing
{
    /**
     * @param  list<array{0: ProductDefinition, 1: int|string, 2: string}>  $lines  [definition, quantity, unit price]
     * @param  array<string, mixed>  $header
     */
    protected function draftPurchase(User $actor, Store $store, OperationalSupplier $supplier, array $lines, array $header = [], Currency $currency = Currency::TRY): Purchase
    {
        $purchase = app(CreatePurchaseDraft::class)($actor, [
            'store_id' => $store->id,
            'operational_supplier_id' => $supplier->id,
            'currency' => $currency->value,
            'tax_treatment' => 'kdv_included',
        ]);

        return app(SavePurchaseDraft::class)($actor, $purchase, $purchase->revision, [
            'store_id' => $store->id,
            'operational_supplier_id' => $supplier->id,
            'currency' => $currency->value,
            'tax_treatment' => 'kdv_included',
            'supplier_invoice_number' => 'INV-1001',
            'invoice_date' => now()->toDateString(),
            'additional_charges' => '',
            'lines' => array_map(fn (array $line): array => [
                'product_definition_id' => $line[0]->id,
                'quantity' => (string) $line[1],
                'unit_cost' => $line[2],
            ], $lines),
            ...$header,
        ]);
    }

    /**
     * @param  list<array{0: ProductDefinition, 1: int|string, 2: string}>  $lines
     * @param  array<string, mixed>  $header
     */
    protected function postedPurchase(User $actor, Store $store, OperationalSupplier $supplier, array $lines, array $header = [], Currency $currency = Currency::TRY): Purchase
    {
        $draft = $this->draftPurchase($actor, $store, $supplier, $lines, $header, $currency);

        return app(PostPurchase::class)($actor, $draft, $draft->revision)->model;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function paymentInput(string $amount, string $reference, array $overrides = []): array
    {
        return [
            'amount' => $amount,
            'paid_on' => now()->toDateString(),
            'method' => 'bank_transfer',
            'reference' => $reference,
            'note' => null,
            ...$overrides,
        ];
    }
}
