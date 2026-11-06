<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Models\InventoryUnit;
use App\Models\PosDiscountGrant;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Support\Facades\Gate;

/**
 * Who may discount a counter line, and how far, read fresh from the current grants.
 *
 * The owner may lower any price to one kuruş with a reason. An employee with the discount
 * permission and an owner-set percentage may lower a price to the list price less that
 * percentage, rounded up to the kuruş, except that a used device with a minimum selling
 * price uses that minimum instead. Discounts only ever lower a price.
 */
final class DiscountAuthority
{
    public const OWNER = 'owner';

    public const PERCENTAGE = 'percentage';

    public const DEVICE_MINIMUM = 'device_minimum';

    private function __construct(
        public readonly int $actorId,
        public readonly bool $isOwner,
        public readonly ?int $basisPoints,
    ) {}

    public static function for(User $actor, Store $store): self
    {
        $gate = Gate::forUser($actor);

        if ($gate->allows('adjustSalePrices', $store)) {
            return new self($actor->id, true, null);
        }

        if ($gate->allows('giveDiscounts', $store)) {
            return new self($actor->id, false, (int) $actor->posDiscountGrant()->value('max_discount_basis_points'));
        }

        return new self($actor->id, false, null);
    }

    public function canDiscount(): bool
    {
        return $this->isOwner || $this->basisPoints !== null;
    }

    /**
     * The limit that applies to one item, or null when the actor cannot discount.
     *
     * @return array{limit: string, lowest: int, basis_points: int|null}|null
     */
    public function limitFor(int $listPrice, ?InventoryUnit $unit): ?array
    {
        if ($this->isOwner) {
            return ['limit' => self::OWNER, 'lowest' => 1, 'basis_points' => null];
        }

        if ($this->basisPoints === null) {
            return null;
        }

        $deviceMinimum = $unit?->minimumSellingPriceMinor();

        if ($deviceMinimum !== null) {
            return ['limit' => self::DEVICE_MINIMUM, 'lowest' => min($deviceMinimum, $listPrice), 'basis_points' => null];
        }

        $full = PosDiscountGrant::FULL_BASIS_POINTS;
        $lowest = intdiv($listPrice * ($full - $this->basisPoints) + $full - 1, $full);

        return ['limit' => self::PERCENTAGE, 'lowest' => max(1, $lowest), 'basis_points' => $this->basisPoints];
    }

    /**
     * Why a discounted price is refused, or null when the limit allows it.
     */
    public function refusal(int $listPrice, int $price, ?InventoryUnit $unit): ?string
    {
        $limit = $this->limitFor($listPrice, $unit);

        if ($limit === null) {
            return __('pos.errors.discount_not_allowed');
        }

        if ($price >= $listPrice) {
            return __('pos.errors.discount_above_list', ['list' => Money::format($listPrice)]);
        }

        if ($price < $limit['lowest']) {
            return __('pos.errors.discount_below_limit', ['price' => Money::format($price), 'lowest' => Money::format($limit['lowest'])]);
        }

        return null;
    }
}
