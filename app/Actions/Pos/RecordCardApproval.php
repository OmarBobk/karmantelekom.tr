<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Enums\CardApprovalStatus;
use App\Enums\Currency;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\CardApproval;
use App\Models\CheckoutAttempt;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Record a card payment that an external terminal has already approved.
 *
 * The approval is stored on the checkout attempt independently of the sale, and freezes the
 * attempt's reviewed payload. The reference must be unique within its terminal; recording the
 * same approval twice returns the original, while a different approval for the same attempt
 * or a reused reference is rejected. No card data is processed or stored.
 */
class RecordCardApproval
{
    public const MAX_REFERENCE_LENGTH = 40;

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, string $attemptKey, int $expectedRevision, int $terminalId, ?string $reference, ?string $amount): CardApproval
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $attempt = ReviewCheckout::findAttempt($store, $attemptKey);
        Gate::forUser($actor)->authorize('operate', $attempt);

        $reference = self::normalizeReference($reference);

        if ($reference === '' || mb_strlen($reference) > self::MAX_REFERENCE_LENGTH || preg_match('/^[A-Z0-9\-\/]+$/', $reference) !== 1) {
            throw ValidationException::withMessages(['card_reference' => __('pos.errors.reference_invalid', ['max' => self::MAX_REFERENCE_LENGTH])]);
        }

        if (self::looksLikeCardNumber($reference)) {
            throw ValidationException::withMessages(['card_reference' => __('pos.errors.reference_card_number')]);
        }

        try {
            $amountMinor = Money::parse((string) $amount, Money::MAX_TOTAL_AMOUNT);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['card_amount' => __('pos.errors.card_amount_invalid')]);
        }

        $terminal = $store->cardTerminals()->active()->whereKey($terminalId)->first();

        if ($terminal === null) {
            throw ValidationException::withMessages(['card_terminal' => __('pos.errors.terminal_unavailable')]);
        }

        try {
            return DB::transaction(function () use ($actor, $attempt, $expectedRevision, $terminal, $reference, $amountMinor): CardApproval {
                $locked = CheckoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                $existing = $locked->lockCardApproval();

                if ($existing !== null) {
                    return self::replay($existing, $actor, $terminal->id, $reference, $amountMinor);
                }

                ReviewCheckout::assertOpen($locked);

                if ($locked->revision !== $expectedRevision) {
                    throw ValidationException::withMessages(['attempt' => __('pos.errors.attempt_changed')]);
                }

                if ($amountMinor > $locked->totalMinor()) {
                    throw ValidationException::withMessages(['card_amount' => __('pos.errors.card_amount_above_total')]);
                }

                $approval = CardApproval::query()->create([
                    'checkout_attempt_id' => $locked->id,
                    'store_id' => $locked->store_id,
                    'card_terminal_id' => $terminal->id,
                    'reference' => $reference,
                    'currency' => Currency::TRY,
                    'amount' => Money::toDecimal($amountMinor),
                    'status' => CardApprovalStatus::APPROVED,
                    'recorded_by' => $actor->id,
                    'recorded_at' => now(),
                ]);

                $locked->update(['frozen_at' => now()]);

                return $approval;
            }, FinalizeCheckout::DEADLOCK_ATTEMPTS);
        } catch (UniqueConstraintViolationException) {
            $existing = $attempt->cardApproval()->first();

            if ($existing !== null) {
                return self::replay($existing, $actor, $terminal->id, $reference, $amountMinor);
            }

            throw ValidationException::withMessages(['card_reference' => __('pos.errors.reference_used')]);
        }
    }

    public static function normalizeReference(?string $reference): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', trim((string) $reference)));
    }

    /**
     * A 13-19 digit value passing the Luhn check is treated as a typed card number and refused.
     */
    public static function looksLikeCardNumber(string $reference): bool
    {
        $digits = str_replace(['-', '/'], '', $reference);

        if (preg_match('/^\d{13,19}$/', $digits) !== 1) {
            return false;
        }

        $sum = 0;

        foreach (array_reverse(str_split($digits)) as $position => $digit) {
            $value = (int) $digit;

            if ($position % 2 === 1) {
                $value *= 2;
                $value = $value > 9 ? $value - 9 : $value;
            }

            $sum += $value;
        }

        return $sum % 10 === 0;
    }

    /**
     * @throws ValidationException
     */
    private static function replay(CardApproval $existing, User $actor, int $terminalId, string $reference, int $amountMinor): CardApproval
    {
        $isSameApproval = $existing->recorded_by === $actor->id
            && $existing->card_terminal_id === $terminalId
            && $existing->reference === $reference
            && $existing->amountMinor() === $amountMinor;

        if (! $isSameApproval) {
            throw ValidationException::withMessages(['card_reference' => __('pos.errors.approval_already_recorded')]);
        }

        return $existing;
    }
}
