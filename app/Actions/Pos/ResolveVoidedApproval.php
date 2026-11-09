<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Actions\Network\ReservationGuard;
use App\Enums\CardApprovalStatus;
use App\Enums\CheckoutAttemptStatus;
use App\Models\CheckoutAttempt;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResolveVoidedApproval
{
    public const MAX_NOTE_LENGTH = 500;

    /**
     * Close an open attempt whose card approval was voided on the terminal.
     *
     * Owner only. The void must already be confirmed outside the app; its terminal reference and
     * a note are required. Nothing is refunded here, and a completed sale cannot be resolved.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, string $attemptKey, ?string $voidReference, ?string $note): CheckoutAttempt
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $attempt = ReviewCheckout::findAttempt($store, $attemptKey);
        Gate::forUser($actor)->authorize('resolve', $attempt);

        $voidReference = RecordCardApproval::normalizeReference($voidReference);
        $note = Str::trim((string) $note);
        $errors = [];

        if ($voidReference === '' || mb_strlen($voidReference) > RecordCardApproval::MAX_REFERENCE_LENGTH || preg_match('/^[A-Z0-9\-\/]+$/', $voidReference) !== 1) {
            $errors['void_reference'] = __('pos.errors.reference_invalid', ['max' => RecordCardApproval::MAX_REFERENCE_LENGTH]);
        }

        if ($note === '' || mb_strlen($note) > self::MAX_NOTE_LENGTH) {
            $errors['void_note'] = __('pos.errors.void_note_required', ['max' => self::MAX_NOTE_LENGTH]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($actor, $store, $attempt, $voidReference, $note): CheckoutAttempt {
            $locked = CheckoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            ReviewCheckout::assertOpen($locked);

            $approval = $locked->lockCardApproval();

            if ($approval === null || $approval->status !== CardApprovalStatus::APPROVED) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.no_approval_to_void')]);
            }

            if ($locked->hasPendingCashIntake()) {
                throw ValidationException::withMessages(['attempt' => __('pos.cashbox.errors.void_with_cash_intake')]);
            }

            $now = now();

            $approval->update([
                'status' => CardApprovalStatus::VOIDED,
                'void_reference' => $voidReference,
                'void_note' => $note,
                'voided_by' => $actor->id,
                'voided_at' => $now,
            ]);

            $locked->update([
                'status' => CheckoutAttemptStatus::VOIDED,
                'resolved_by' => $actor->id,
                'resolved_at' => $now,
                'resolution_note' => $note,
            ]);

            ReservationGuard::releaseClaim($locked, $actor);

            activity('checkout_resolution')
                ->performedOn($locked)
                ->causedBy($actor)
                ->event('card_approval_voided')
                ->withProperties([
                    'store_id' => $store->id,
                    'approval_id' => $approval->id,
                    'terminal_id' => $approval->card_terminal_id,
                    'reference' => $approval->reference,
                    'amount' => $approval->amount,
                    'void_reference' => $voidReference,
                ])
                ->log("Card approval {$approval->reference} on attempt {$locked->key} recorded as voided");

            return $locked;
        });
    }
}
