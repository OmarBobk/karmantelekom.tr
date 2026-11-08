<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\CheckoutAttempt;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Record that the cashier is about to charge a card on an external terminal.
 *
 * Saved before the counter tells the cashier to charge, so the interval between charging and
 * recording the approval is durable: the reviewed cart freezes, and until the matching approval
 * is recorded or the owner confirms on the terminal that nothing was charged, the attempt can be
 * neither edited, cancelled, nor completed with cash only.
 *
 * The charge cannot exceed the reviewed total less its credit, and a credited checkout needs the
 * cashier's current credit authority, because once frozen it is completed as reviewed.
 *
 * Every new start gets its own key, which approvals and resolutions must name. The caller's
 * request key makes a retried request a replay (not a new start); any other start while one is
 * current is refused.
 */
class StartTerminalPayment
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, string $attemptKey, int $expectedRevision, string $requestKey, int $terminalId, ?string $amount): TerminalPaymentStart
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $attempt = ReviewCheckout::findAttempt($store, $attemptKey);
        Gate::forUser($actor)->authorize('operate', $attempt);

        if (! Str::isUuid($requestKey)) {
            throw ValidationException::withMessages(['attempt' => __('pos.errors.attempt_changed')]);
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

        return DB::transaction(function () use ($actor, $attempt, $expectedRevision, $requestKey, $terminal, $amountMinor): TerminalPaymentStart {
            $locked = CheckoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            ReviewCheckout::assertOpen($locked);

            if ($locked->hasStartedTerminalPayment()) {
                $isSameRequest = $locked->terminal_payment_request_key === $requestKey
                    && $locked->terminal_payment_terminal_id === $terminal->id
                    && $locked->terminalPaymentAmountMinor() === $amountMinor;

                if (! $isSameRequest) {
                    throw ValidationException::withMessages(['attempt' => __('pos.errors.terminal_payment_already_started')]);
                }

                return new TerminalPaymentStart($locked, isNewStart: false);
            }

            if ($locked->cardApproval()->exists()) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.approval_already_recorded')]);
            }

            if ($locked->hasPendingCashIntake()) {
                throw ValidationException::withMessages(['attempt' => __('pos.cashbox.errors.card_after_cash_intake')]);
            }

            if ($locked->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.attempt_changed')]);
            }

            if ($locked->creditMinor() > 0) {
                ReviewCheckout::assertCreditAllowed($actor, $locked->store, $locked->customer_id);
            }

            if ($amountMinor > $locked->payableMinor()) {
                throw ValidationException::withMessages(['card_amount' => $locked->creditMinor() > 0
                    ? __('pos.credit.errors.card_above_payable', ['amount' => LocalizedMoney::format($locked->payableMinor())])
                    : __('pos.errors.card_amount_above_total')]);
            }

            self::assertReviewedPricesCurrent($actor, $locked);

            $now = now();

            $locked->update([
                'frozen_at' => $locked->frozen_at ?? $now,
                'terminal_payment_key' => (string) Str::uuid(),
                'terminal_payment_request_key' => $requestKey,
                'terminal_payment_terminal_id' => $terminal->id,
                'terminal_payment_amount' => Money::toDecimal($amountMinor),
                'terminal_payment_started_by' => $actor->id,
                'terminal_payment_started_at' => $now,
            ]);

            return new TerminalPaymentStart($locked, isNewStart: true);
        }, FinalizeCheckout::DEADLOCK_ATTEMPTS);
    }

    /**
     * Refuse to freeze a cart whose list prices or discount limits changed since review.
     *
     * Once a card or cash payment start is saved the total is final, so this is the last point
     * where a stricter limit or a new price can send the cashier back to review instead of paying.
     *
     * @throws ValidationException
     */
    public static function assertReviewedPricesCurrent(User $actor, CheckoutAttempt $attempt): void
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = $attempt->lines;
        $store = $attempt->store;
        $definitions = ProductDefinition::query()->whereKey(array_map(fn (array $line): int => (int) $line['definition_id'], $lines))->get()->keyBy('id');
        $units = $store->units()->whereKey(array_filter(array_map(fn (array $line): ?int => $line['unit_id'] === null ? null : (int) $line['unit_id'], $lines)))->get()->keyBy('id');
        $authority = DiscountAuthority::for($actor, $store);
        $errors = [];

        foreach ($lines as $line) {
            $definition = $definitions->get((int) $line['definition_id']);
            $unit = $line['unit_id'] === null ? null : $units->get((int) $line['unit_id']);
            $listPrice = (int) $line['list_unit_price'];
            $unitPrice = (int) $line['unit_price'];

            if ($definition === null || SellingPrice::listPriceMinor($definition, $unit) !== $listPrice) {
                $errors["lines.{$line['key']}.price"] = __('pos.errors.price_changed', ['name' => $line['name']]);
            } elseif ($unitPrice < $listPrice && $authority->refusal($listPrice, $unitPrice, $unit) !== null) {
                $errors["lines.{$line['key']}.adjusted_price"] = __('pos.errors.discount_changed', ['name' => $line['name']]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['attempt' => __('pos.errors.review_again_before_payment'), ...$errors]);
        }
    }
}
