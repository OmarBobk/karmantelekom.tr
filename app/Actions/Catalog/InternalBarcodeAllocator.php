<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CatalogBarcodeSequence;
use App\Models\InventoryUnitIdentifier;
use App\Models\ProductDefinition;
use Illuminate\Validation\ValidationException;

/**
 * Allocates the next free shared internal barcode in 200001–299999.
 *
 * Call only inside an open transaction. Lock the singleton sequence first (see
 * {@see self::lockSequence()}) before allocate() or in-band availability checks so
 * generated barcodes and manual in-band SKU/barcode writes serialize together.
 */
final class InternalBarcodeAllocator
{
    public const MIN = 200001;

    public const MAX = 299999;

    /**
     * Six-digit ASCII decimal strings inside the internal allocation band.
     */
    public static function isInternalRange(?string $value): bool
    {
        if ($value === null || preg_match('/^[0-9]{6}$/', $value) !== 1) {
            return false;
        }

        $number = (int) $value;

        return $number >= self::MIN && $number <= self::MAX;
    }

    /**
     * Exclusive gate for allocation and in-band identifier claims.
     */
    public function lockSequence(): CatalogBarcodeSequence
    {
        $now = now();

        CatalogBarcodeSequence::query()->insertOrIgnore([
            'id' => CatalogBarcodeSequence::SINGLETON_ID,
            'last_number' => self::MIN - 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return CatalogBarcodeSequence::query()
            ->whereKey(CatalogBarcodeSequence::SINGLETON_ID)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  list<string>  $alsoReserved
     *
     * @throws ValidationException
     */
    public function allocate(array $alsoReserved = []): string
    {
        $sequence = $this->lockSequence();

        $candidate = max($sequence->last_number + 1, self::MIN);

        while ($candidate <= self::MAX) {
            $code = (string) $candidate;

            if (! $this->isTaken($code, $alsoReserved)) {
                $sequence->last_number = $candidate;
                $sequence->save();

                return $code;
            }

            $candidate++;
        }

        throw ValidationException::withMessages([
            'barcode' => __('catalog.errors.internal_barcode_exhausted'),
        ]);
    }

    /**
     * Reject an in-range catalog identifier that already names a different target.
     *
     * Must run after {@see self::lockSequence()} in the same transaction. Outside the band,
     * callers keep the ordinary per-column unique rules.
     *
     * @throws ValidationException
     */
    public function assertCatalogIdentifierAvailable(string $field, string $identifier, ?int $ignoreDefinitionId = null): void
    {
        if (! self::isInternalRange($identifier)) {
            return;
        }

        if ($this->isTaken($identifier, [], $ignoreDefinitionId)) {
            throw ValidationException::withMessages([
                $field => __('catalog.errors.identifier_conflict'),
            ]);
        }
    }

    /**
     * @param  list<string>  $alsoReserved
     */
    public function isTaken(string $code, array $alsoReserved = [], ?int $ignoreDefinitionId = null): bool
    {
        foreach ($alsoReserved as $reserved) {
            if ($reserved === $code) {
                return true;
            }
        }

        // Locking reads see the latest committed row after waiting on the sequence gate.
        $definitionTaken = ProductDefinition::query()
            ->when($ignoreDefinitionId !== null, fn ($query) => $query->whereKeyNot($ignoreDefinitionId))
            ->where(fn ($query) => $query->where('sku', $code)->orWhere('barcode', $code))
            ->lockForUpdate()
            ->exists();

        if ($definitionTaken) {
            return true;
        }

        return InventoryUnitIdentifier::query()->where('value', $code)->lockForUpdate()->exists();
    }
}
