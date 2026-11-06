<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Enums\TerminalPaymentOutcome;
use App\Models\CheckoutAttempt;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResolveTerminalPayment
{
    /**
     * Clear a started terminal payment that never produced an approval in the app.
     *
     * Owner only, after checking the terminal: either it shows no charge, or the charge was
     * voided there (its void reference is then required). A note is always required and the
     * resolution is logged. It names the start it resolves: a dialog showing an earlier start is
     * refused, while a retry of the resolution that cleared this start returns the attempt. The attempt stays open with its reviewed cart unfrozen, so the
     * cashier can take another payment, edit, or cancel. A started payment with a recorded
     * approval is resolved through ResolveVoidedApproval instead.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, string $attemptKey, string $terminalPaymentKey, ?string $outcome, ?string $voidReference, ?string $note): CheckoutAttempt
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $attempt = ReviewCheckout::findAttempt($store, $attemptKey);
        Gate::forUser($actor)->authorize('resolve', $attempt);

        $outcome = TerminalPaymentOutcome::tryFrom((string) $outcome);
        $voidReference = RecordCardApproval::normalizeReference($voidReference);
        $note = Str::trim((string) $note);
        $errors = [];

        if ($outcome === null) {
            $errors['terminal_outcome'] = __('pos.errors.terminal_outcome_required');
        }

        if ($outcome === TerminalPaymentOutcome::VOIDED && ($voidReference === '' || mb_strlen($voidReference) > RecordCardApproval::MAX_REFERENCE_LENGTH || preg_match('/^[A-Z0-9\-\/]+$/', $voidReference) !== 1)) {
            $errors['void_reference'] = __('pos.errors.reference_invalid', ['max' => RecordCardApproval::MAX_REFERENCE_LENGTH]);
        }

        if ($note === '' || mb_strlen($note) > ResolveVoidedApproval::MAX_NOTE_LENGTH) {
            $errors['void_note'] = __('pos.errors.void_note_required', ['max' => ResolveVoidedApproval::MAX_NOTE_LENGTH]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($actor, $store, $attempt, $terminalPaymentKey, $outcome, $voidReference, $note): CheckoutAttempt {
            $locked = CheckoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            ReviewCheckout::assertOpen($locked);

            if ($locked->lockCardApproval() !== null) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.terminal_payment_has_approval')]);
            }

            if (! $locked->hasStartedTerminalPayment()) {
                if ($locked->terminal_payment_cleared_key !== null && hash_equals($locked->terminal_payment_cleared_key, $terminalPaymentKey)) {
                    return $locked;
                }

                throw ValidationException::withMessages(['attempt' => __('pos.errors.no_terminal_payment')]);
            }

            if (! hash_equals((string) $locked->terminal_payment_key, $terminalPaymentKey)) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.terminal_payment_stale')]);
            }

            activity('checkout_resolution')
                ->performedOn($locked)
                ->causedBy($actor)
                ->event('terminal_payment_cleared')
                ->withProperties([
                    'store_id' => $store->id,
                    'terminal_payment_key' => $locked->terminal_payment_key,
                    'outcome' => $outcome->value,
                    'terminal_id' => $locked->terminal_payment_terminal_id,
                    'amount' => $locked->terminal_payment_amount,
                    'started_by' => $locked->terminal_payment_started_by,
                    'started_at' => $locked->terminal_payment_started_at?->toIso8601String(),
                    'void_reference' => $outcome === TerminalPaymentOutcome::VOIDED ? $voidReference : null,
                    'note' => $note,
                ])
                ->log("Terminal payment of {$locked->terminal_payment_amount} on attempt {$locked->key} cleared: {$outcome->value}");

            $locked->update([
                'frozen_at' => null,
                'terminal_payment_cleared_key' => $locked->terminal_payment_key,
                'terminal_payment_key' => null,
                'terminal_payment_request_key' => null,
                'terminal_payment_terminal_id' => null,
                'terminal_payment_amount' => null,
                'terminal_payment_started_by' => null,
                'terminal_payment_started_at' => null,
            ]);

            return $locked;
        });
    }
}
