<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Actions\Pos\SellingPrice;
use App\Enums\TrackingMode;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\ProductDefinition;
use App\Models\ProductDefinitionSubmission;
use App\Models\User;
use App\Services\Idempotency;
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
    public function __construct(private readonly InternalBarcodeAllocator $allocator) {}

    /**
     * Create or update a shared product definition. Never creates stock.
     *
     * On create, a blank barcode receives the next free internal code. On update, a blank
     * submitted barcode never clears an already-stored value (stale-form protection) and never
     * auto-generates — use GenerateInternalBarcode for that. An optional request key makes a
     * lost-response create retry return the same definition and code.
     *
     * The tracking mode is rechecked under the definition row lock that every stock
     * mutation also takes, and cannot change once any store has inventory history.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException
     */
    public function __invoke(User $actor, ?ProductDefinition $definition, array $input, ?string $requestKey = null): ProductDefinition
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

        if (isset($attributes['sku'])) {
            $this->allocator->assertCatalogIdentifierAvailable('sku', $attributes['sku'], $definition?->id);
        }

        if (isset($attributes['barcode'])) {
            $this->allocator->assertCatalogIdentifierAvailable('barcode', $attributes['barcode'], $definition?->id);
        }

        if ($definition === null) {
            return $this->create($actor, $attributes, $requestKey);
        }

        return $this->update($actor, $definition, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws IdempotencyConflictException
     * @throws ValidationException
     */
    private function create(User $actor, array $attributes, ?string $requestKey): ProductDefinition
    {
        $key = $requestKey === null ? null : Idempotency::key($requestKey);
        $fingerprint = Idempotency::fingerprint([
            'operation' => 'create_product_definition',
            'attributes' => $attributes,
        ]);

        if ($key !== null) {
            $existing = ProductDefinitionSubmission::query()->where('request_key', $key)->first();

            if ($existing !== null) {
                return $this->replayCreate($existing, $actor, $fingerprint);
            }
        }

        $allocateBarcode = ($attributes['barcode'] ?? null) === null;
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($actor, $attributes, $key, $fingerprint, $allocateBarcode): ProductDefinition {
                    if ($key !== null) {
                        $existing = ProductDefinitionSubmission::query()->where('request_key', $key)->first();

                        if ($existing !== null) {
                            return $this->replayCreate($existing, $actor, $fingerprint);
                        }
                    }

                    $payload = $attributes;

                    if ($allocateBarcode) {
                        $reserved = array_values(array_filter([
                            $payload['sku'] ?? null,
                        ], fn (?string $value): bool => $value !== null));
                        $payload['barcode'] = $this->allocator->allocate($reserved);
                    }

                    $created = ProductDefinition::query()->create($payload);
                    $this->logPriceChange($actor, $created, null);

                    if ($key !== null) {
                        ProductDefinitionSubmission::query()->create([
                            'user_id' => $actor->id,
                            'request_key' => $key,
                            'request_fingerprint' => $fingerprint,
                            'product_definition_id' => $created->id,
                            'submitted_at' => now(),
                        ]);
                    }

                    return $created;
                }, attempts: 5);
            } catch (UniqueConstraintViolationException $exception) {
                if ($key !== null) {
                    $existing = ProductDefinitionSubmission::query()->where('request_key', $key)->first();

                    if ($existing !== null) {
                        return $this->replayCreate($existing, $actor, $fingerprint);
                    }
                }

                if ($allocateBarcode && ++$attempts < 5) {
                    continue;
                }

                throw ValidationException::withMessages([
                    $this->uniqueFieldFromException($exception) => __('catalog.errors.identifier_taken'),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    private function update(User $actor, ProductDefinition $definition, array $attributes): ProductDefinition
    {
        try {
            // Checkout reads prices under this same definition lock, so a price change and a sale serialize.
            return DB::transaction(function () use ($actor, $definition, $attributes): ProductDefinition {
                $locked = ProductDefinition::query()->whereKey($definition->id)->lockForUpdate()->firstOrFail();

                if ($locked->tracking_mode->value !== $attributes['tracking_mode'] && $locked->isTrackingModeLocked()) {
                    throw ValidationException::withMessages([
                        'tracking_mode' => __('catalog.errors.tracking_mode_locked'),
                    ]);
                }

                // A blank edit must not wipe a barcode assigned after this form was opened.
                if (($attributes['barcode'] ?? null) === null && $locked->barcode !== null) {
                    $attributes['barcode'] = $locked->barcode;
                }

                $previousPrice = $locked->selling_price;
                $locked->update($attributes);
                $this->logPriceChange($actor, $locked, $previousPrice);

                return $locked;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                $this->uniqueFieldFromException($exception) => __('catalog.errors.identifier_taken'),
            ]);
        }
    }

    /**
     * @throws IdempotencyConflictException
     */
    private function replayCreate(ProductDefinitionSubmission $submission, User $actor, string $fingerprint): ProductDefinition
    {
        Idempotency::assertReplay($submission->user_id, $submission->request_fingerprint, $actor, $fingerprint);

        return ProductDefinition::query()->findOrFail($submission->product_definition_id);
    }

    private function uniqueFieldFromException(UniqueConstraintViolationException $exception): string
    {
        $message = Str::lower($exception->getMessage());

        if (str_contains($message, 'barcode')) {
            return 'barcode';
        }

        return 'sku';
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
