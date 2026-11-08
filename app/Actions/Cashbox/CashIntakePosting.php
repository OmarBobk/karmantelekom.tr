<?php

declare(strict_types=1);

namespace App\Actions\Cashbox;

use App\Enums\CashIntakeSource;
use App\Enums\CashIntakeStatus;
use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Models\CashboxSession;
use App\Models\CashIntake;
use App\Models\CashMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Binding cash to the open session before it is accepted, and posting it when its sale or
 * customer payment completes. Both run inside the caller's transaction, after the caller's
 * source lock (checkout attempt or customer row); the store row's share lock is always the last
 * lock taken, matching CloseCashboxSession, which locks the store row exclusively and first.
 */
final class CashIntakePosting
{
    /**
     * Create a pending intake on the store's open session.
     *
     * @throws ValidationException when no session is open.
     */
    public static function bind(User $actor, Store $store, CashIntakeSource $source, int $sourceId, int $amountMinor, string $requestKey): CashIntake
    {
        $session = CashboxSession::lockOpenShared($store);

        if ($session === null) {
            throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.errors.no_open_session')]);
        }

        return CashIntake::query()->create([
            'store_id' => $store->id,
            'cashbox_session_id' => $session->id,
            'source_type' => $source,
            'checkout_attempt_id' => $source === CashIntakeSource::SALE ? $sourceId : null,
            'customer_id' => $source === CashIntakeSource::CUSTOMER_PAYMENT ? $sourceId : null,
            'amount' => Money::toDecimal($amountMinor),
            'status' => CashIntakeStatus::PENDING,
            'pending_source' => $source === CashIntakeSource::SALE ? CashIntake::attemptSource($sourceId) : CashIntake::customerSource($sourceId),
            'request_key' => $requestKey,
            'started_by' => $actor->id,
            'started_at' => now(),
        ]);
    }

    /**
     * Post the intake's cash to its session as one movement and complete the intake. The intake
     * must be row-locked and pending; a pending intake keeps its session open, so the session is
     * still the open one.
     *
     * @param  array{sale_payment_id?: int, customer_payment_id?: int}  $source
     */
    public static function post(User $actor, Store $store, CashIntake $intake, CashMovementType $type, int $amountMinor, array $source, mixed $now): CashMovement
    {
        $session = CashboxSession::lockOpenShared($store);

        if ($session === null || $session->id !== $intake->cashbox_session_id) {
            throw new LogicException("Cash intake {$intake->id} is pending on a session that is not open.");
        }

        $movement = CashMovement::query()->create([
            'store_id' => $store->id,
            'cashbox_session_id' => $session->id,
            'type' => $type,
            'direction' => CashMovementDirection::IN,
            'amount' => Money::toDecimal($amountMinor),
            'cash_intake_id' => $intake->id,
            'sale_payment_id' => $source['sale_payment_id'] ?? null,
            'customer_payment_id' => $source['customer_payment_id'] ?? null,
            'user_id' => $actor->id,
            'occurred_at' => $now,
        ]);

        $intake->update([
            'status' => CashIntakeStatus::COMPLETED,
            'pending_source' => null,
            'completed_at' => $now,
        ]);

        return $movement;
    }
}
