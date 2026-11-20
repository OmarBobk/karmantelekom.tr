<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ProductDefinition;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Assign one internal barcode to a definition that still has none.
 *
 * Idempotent under the definition row lock: a retry returns the persisted code.
 * Never changes stock, price, or tracking mode.
 */
final class GenerateInternalBarcode
{
    public function __construct(private readonly InternalBarcodeAllocator $allocator) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, ProductDefinition $definition): ProductDefinition
    {
        Gate::forUser($actor)->authorize('update', $definition);

        return DB::transaction(function () use ($definition): ProductDefinition {
            $locked = ProductDefinition::query()->whereKey($definition->id)->lockForUpdate()->firstOrFail();

            if ($locked->barcode !== null) {
                return $locked;
            }

            $locked->update(['barcode' => $this->allocator->allocate()]);

            return $locked;
        }, attempts: 5);
    }
}
