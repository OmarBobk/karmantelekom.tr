<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\TrackingMode;
use App\Models\ProductDefinition;
use App\Models\User;
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

        try {
            if ($definition === null) {
                return ProductDefinition::query()->create($attributes);
            }

            return DB::transaction(function () use ($definition, $attributes): ProductDefinition {
                $locked = ProductDefinition::query()->whereKey($definition->id)->lockForUpdate()->firstOrFail();

                if ($locked->tracking_mode->value !== $attributes['tracking_mode'] && $locked->isTrackingModeLocked()) {
                    throw ValidationException::withMessages([
                        'tracking_mode' => __('catalog.errors.tracking_mode_locked'),
                    ]);
                }

                $locked->update($attributes);

                return $locked;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['sku' => __('catalog.errors.identifier_taken')]);
        }
    }

    private static function nullableTrim(mixed $value): ?string
    {
        $value = Str::trim((string) $value);

        return $value === '' ? null : $value;
    }
}
