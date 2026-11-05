<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\Currency;
use App\Enums\PurchaseStatus;
use App\Enums\PurchaseTaxTreatment;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreatePurchaseDraft
{
    /**
     * Start an empty supplier invoice draft in a store the actor can access.
     *
     * @param  array{store_id?: mixed, operational_supplier_id?: mixed, currency?: mixed, tax_treatment?: mixed}  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, array $input): Purchase
    {
        Gate::forUser($actor)->authorize('create', Purchase::class);

        $validated = Validator::make($input, [
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')],
            'operational_supplier_id' => ['required', 'integer', Rule::exists('operational_suppliers', 'id')->where('is_active', true)],
            'currency' => ['required', Rule::enum(Currency::class)],
            'tax_treatment' => ['required', Rule::enum(PurchaseTaxTreatment::class)],
        ], PurchaseAttributes::messages(), PurchaseAttributes::header())->validate();

        Gate::forUser($actor)->authorize('createIn', [Purchase::class, Store::query()->findOrFail($validated['store_id'])]);

        return Purchase::query()->create([
            'store_id' => $validated['store_id'],
            'operational_supplier_id' => $validated['operational_supplier_id'],
            'currency' => $validated['currency'],
            'tax_treatment' => $validated['tax_treatment'],
            'status' => PurchaseStatus::DRAFT,
            'revision' => 1,
            'created_by' => $actor->id,
        ]);
    }
}
