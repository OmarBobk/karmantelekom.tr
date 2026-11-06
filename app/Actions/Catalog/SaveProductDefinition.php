<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Actions\Pos\SellingPrice;
use App\Enums\TrackingMode;
use App\Models\ProductDefinition;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveProductDefinition
{
    /**
     * Create or update a shared product definition. Never creates stock.
     *
     * The tracking mode is rechecked under the definition row lock that every stock
     * mutation also takes, and cannot change once any store has inventory history.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, ?ProductDefinition $definition, array $input): ProductDefinition
    {
        $definition === null
            ? Gate::forUser($actor)->authorize('create', ProductDefinition::class)
            : Gate::forUser($actor)->authorize('update', $definition);

        $setsPrice = array_key_exists('selling_price', $input);

        if ($setsPrice) {
            Gate::forUser($actor)->authorize('setSellingPrice', ProductDefinition::class);
        }

        $sellingPriceMinor = $setsPrice ? SellingPrice::parse(is_scalar($input['selling_price']) ? (string) $input['selling_price'] : null, 'selling_price') : null;
        unset($input['selling_price']);

        $input['name'] = Str::trim((string) ($input['name'] ?? ''));
        $input['model'] = self::nullableTrim($input['model'] ?? null);
        $input['description'] = self::nullableTrim($input['description'] ?? null);
        $input['sku'] = ProductDefinition::normalizeIdentifier($input['sku'] ?? null);
        $input['barcode'] = ProductDefinition::normalizeIdentifier($input['barcode'] ?? null);

        $attributes = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'tracking_mode' => ['required', Rule::enum(TrackingMode::class)],
            'is_active' => ['boolean'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('product_definitions', 'sku')->ignore($definition?->id)],
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('product_definitions', 'barcode')->ignore($definition?->id)],
            'catalog_brand_id' => ['nullable', 'integer', Rule::exists('catalog_brands', 'id')],
            'catalog_category_id' => ['nullable', 'integer', Rule::exists('catalog_categories', 'id')],
        ], is_array($messages = __('operations.validation')) ? $messages : [], [
            'name' => __('catalog.fields.name'),
            'model' => __('catalog.fields.model'),
            'description' => __('catalog.fields.description'),
            'tracking_mode' => __('catalog.fields.tracking_mode'),
            'sku' => __('catalog.fields.sku'),
            'barcode' => __('catalog.fields.barcode'),
            'catalog_brand_id' => __('catalog.fields.brand'),
            'catalog_category_id' => __('catalog.fields.category'),
        ])->validate();

        $isUsedDevice = $attributes['tracking_mode'] === TrackingMode::USED_DEVICE->value;

        if ($setsPrice && $isUsedDevice && $sellingPriceMinor !== null) {
            throw ValidationException::withMessages(['selling_price' => __('pos.errors.used_device_definition_price')]);
        }

        if ($setsPrice || $isUsedDevice) {
            $attributes['selling_price'] = $sellingPriceMinor === null ? null : Money::toDecimal($sellingPriceMinor);
        }

        try {
            if ($definition === null) {
                $created = ProductDefinition::query()->create($attributes);
                $this->logPriceChange($actor, $created, null);

                return $created;
            }

            // Checkout reads prices under this same definition lock, so a price change and a sale serialize.
            return DB::transaction(function () use ($actor, $definition, $attributes): ProductDefinition {
                $locked = ProductDefinition::query()->whereKey($definition->id)->lockForUpdate()->firstOrFail();

                if ($locked->tracking_mode->value !== $attributes['tracking_mode'] && $locked->isTrackingModeLocked()) {
                    throw ValidationException::withMessages([
                        'tracking_mode' => __('catalog.errors.tracking_mode_locked'),
                    ]);
                }

                $previousPrice = $locked->selling_price;
                $locked->update($attributes);
                $this->logPriceChange($actor, $locked, $previousPrice);

                return $locked;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['sku' => __('catalog.errors.identifier_taken')]);
        }
    }

    private function logPriceChange(User $actor, ProductDefinition $definition, ?string $previousPrice): void
    {
        if ($definition->selling_price === $previousPrice) {
            return;
        }

        activity('selling_price')
            ->performedOn($definition)
            ->causedBy($actor)
            ->event('definition_price_set')
            ->withProperties(['old' => $previousPrice, 'new' => $definition->selling_price, 'currency' => 'TRY'])
            ->log("Selling price of {$definition->name} set to ".($definition->selling_price ?? 'not priced'));
    }

    private static function nullableTrim(mixed $value): ?string
    {
        $value = Str::trim((string) $value);

        return $value === '' ? null : $value;
    }
}
