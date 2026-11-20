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
     * On create, a blank barcode receives the next free internal code. On update, blank
     * barcodes do not auto-generate — use GenerateInternalBarcode. A create request key
     * replays the same definition before uniqueness rules can reject the original SKU or
     * barcode. In-band identifier claims and allocation take the shared sequence lock first.
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
        $hasExpectedBarcode = array_key_exists('expected_barcode', $input);
        $expectedBarcode = $hasExpectedBarcode
            ? ProductDefinition::normalizeIdentifier(is_scalar($input['expected_barcode']) || $input['expected_barcode'] === null
                ? $input['expected_barcode']
                : null)
            : null;
        unset($input['expected_barcode']);

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

        $fingerprintAttributes = [
            'name' => $input['name'],
            'model' => $input['model'],
            'description' => $input['description'],
            'tracking_mode' => $input['tracking_mode'] ?? null,
            'is_active' => array_key_exists('is_active', $input) ? (bool) $input['is_active'] : true,
            'sku' => $input['sku'],
            'barcode' => $input['barcode'],
            'catalog_brand_id' => $input['catalog_brand_id'] ?? null,
            'catalog_category_id' => $input['catalog_category_id'] ?? null,
        ];

        if ($setsPrice) {
            $fingerprintAttributes['selling_price'] = $sellingPriceMinor === null ? null : Money::toDecimal($sellingPriceMinor);
        }

        $key = ($definition === null && $requestKey !== null) ? Idempotency::key($requestKey) : null;
        $fingerprint = $key === null ? null : Idempotency::fingerprint([
            'operation' => 'create_product_definition',
            'attributes' => $fingerprintAttributes,
        ]);

        // Replay before mutable uniqueness rules can reject the original submission's identifiers.
        if ($key !== null && $fingerprint !== null) {
            $existing = ProductDefinitionSubmission::query()->where('request_key', $key)->first();

            if ($existing !== null) {
                return $this->replayCreate($existing, $actor, $fingerprint);
            }
        }

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

        if ($definition === null) {
            return $this->create($actor, $attributes, $key, $fingerprint);
        }

        return $this->update($actor, $definition, $attributes, $hasExpectedBarcode, $expectedBarcode);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws IdempotencyConflictException
     * @throws ValidationException
     */
    private function create(User $actor, array $attributes, ?string $key, ?string $fingerprint): ProductDefinition
    {
        $allocateBarcode = ($attributes['barcode'] ?? null) === null;
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($actor, $attributes, $key, $fingerprint, $allocateBarcode): ProductDefinition {
                    if ($key !== null && $fingerprint !== null) {
                        $existing = ProductDefinitionSubmission::query()->where('request_key', $key)->first();

                        if ($existing !== null) {
                            return $this->replayCreate($existing, $actor, $fingerprint);
                        }
                    }

                    $payload = $attributes;
                    $needsSequence = $allocateBarcode
                        || InternalBarcodeAllocator::isInternalRange($payload['sku'] ?? null)
                        || InternalBarcodeAllocator::isInternalRange($payload['barcode'] ?? null);

                    if ($needsSequence) {
                        $this->allocator->lockSequence();
                    }

                    if ($allocateBarcode) {
                        $reserved = array_values(array_filter([
                            $payload['sku'] ?? null,
                        ], fn (?string $value): bool => $value !== null));
                        $payload['barcode'] = $this->allocator->allocate($reserved);
                    }

                    $this->assertInBandIfPresent('sku', $payload['sku'] ?? null);
                    if (! $allocateBarcode) {
                        $this->assertInBandIfPresent('barcode', $payload['barcode'] ?? null);
                    }

                    $created = ProductDefinition::query()->create($payload);
                    $this->logPriceChange($actor, $created, null);

                    if ($key !== null && $fingerprint !== null) {
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
                if ($key !== null && $fingerprint !== null) {
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
    private function update(
        User $actor,
        ProductDefinition $definition,
        array $attributes,
        bool $hasExpectedBarcode,
        ?string $expectedBarcode,
    ): ProductDefinition {
        try {
            // Lock order: sequence (when claiming in-band identifiers) then definition.
            return DB::transaction(function () use ($actor, $definition, $attributes, $hasExpectedBarcode, $expectedBarcode): ProductDefinition {
                $submittedBarcode = $attributes['barcode'] ?? null;
                $submittedSku = $attributes['sku'] ?? null;

                $mayTouchInBand = InternalBarcodeAllocator::isInternalRange($submittedSku)
                    || InternalBarcodeAllocator::isInternalRange($submittedBarcode);

                if ($mayTouchInBand) {
                    $this->allocator->lockSequence();
                }

                $locked = ProductDefinition::query()->whereKey($definition->id)->lockForUpdate()->firstOrFail();

                if ($locked->tracking_mode->value !== $attributes['tracking_mode'] && $locked->isTrackingModeLocked()) {
                    throw ValidationException::withMessages([
                        'tracking_mode' => __('catalog.errors.tracking_mode_locked'),
                    ]);
                }

                $attributes['barcode'] = $this->resolveUpdatedBarcode(
                    $submittedBarcode,
                    $locked->barcode,
                    $hasExpectedBarcode,
                    $expectedBarcode,
                );

                if (($attributes['sku'] ?? null) !== $locked->sku) {
                    $this->assertInBandIfPresent('sku', $attributes['sku'] ?? null, $locked->id);
                }

                if (($attributes['barcode'] ?? null) !== $locked->barcode) {
                    $this->assertInBandIfPresent('barcode', $attributes['barcode'] ?? null, $locked->id);
                }

                // A newly in-band identifier needs the sequence lock even when the pre-check above
                // used the submitted (pre-resolution) values — re-lock is a no-op when already held.
                if (
                    ! $mayTouchInBand
                    && (
                        InternalBarcodeAllocator::isInternalRange($attributes['sku'] ?? null)
                        || InternalBarcodeAllocator::isInternalRange($attributes['barcode'] ?? null)
                    )
                ) {
                    $this->allocator->lockSequence();
                }

                $previousPrice = $locked->selling_price;
                $locked->update($attributes);
                $this->logPriceChange($actor, $locked, $previousPrice);

                return $locked;
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                $this->uniqueFieldFromException($exception) => __('catalog.errors.identifier_taken'),
            ]);
        }
    }

    /**
     * Stale blank form (opened with null, barcode assigned later) keeps the stored value.
     * A fresh form that shows the current barcode and clears it intentionally stores null.
     * Callers that omit expected_barcode keep the previous clear-on-blank edit behavior.
     */
    private function resolveUpdatedBarcode(
        ?string $submittedBarcode,
        ?string $lockedBarcode,
        bool $hasExpectedBarcode,
        ?string $expectedBarcode,
    ): ?string {
        if ($submittedBarcode !== null || $lockedBarcode === null) {
            return $submittedBarcode;
        }

        if (! $hasExpectedBarcode) {
            return null;
        }

        if ($expectedBarcode === null) {
            return $lockedBarcode;
        }

        if ($expectedBarcode === $lockedBarcode) {
            return null;
        }

        return $lockedBarcode;
    }

    /**
     * @throws ValidationException
     */
    private function assertInBandIfPresent(string $field, ?string $identifier, ?int $ignoreDefinitionId = null): void
    {
        if ($identifier === null) {
            return;
        }

        $this->allocator->assertCatalogIdentifierAvailable($field, $identifier, $ignoreDefinitionId);
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
