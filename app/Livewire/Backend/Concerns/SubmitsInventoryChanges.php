<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Concerns;

use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\InventoryMovement;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Server-issued idempotency key for inventory forms.
 *
 * The key lives in the component snapshot, so a retried request carries the same key,
 * and it is replaced only after a submission is recorded or conflicts.
 */
trait SubmitsInventoryChanges
{
    #[Locked]
    public string $submissionKey = '';

    public function mountSubmitsInventoryChanges(): void
    {
        $this->renewSubmissionKey();
    }

    protected function renewSubmissionKey(): void
    {
        $this->submissionKey = (string) Str::uuid();
    }

    /**
     * Run an inventory action, mapping its error keys onto this form's properties.
     *
     * @param  Closure(): ?InventoryMovement  $action
     * @param  array<string, string>  $errorMap  Action error key => component property.
     * @param  string|null  $unchangedMessage  Shown when the action completes without writing a movement.
     *
     * @throws ValidationException
     */
    protected function submitInventoryChange(Closure $action, array $errorMap, string $fallbackErrorKey, ?string $unchangedMessage = null): ?InventoryMovement
    {
        $this->resetErrorBag([...array_values($errorMap), $fallbackErrorKey]);

        try {
            $movement = $action();
        } catch (ValidationException $exception) {
            $messages = [];

            foreach ($exception->errors() as $key => $keyMessages) {
                $messages[$errorMap[$key] ?? $fallbackErrorKey] = $keyMessages;
            }

            throw ValidationException::withMessages($messages);
        } catch (IdempotencyConflictException) {
            $this->renewSubmissionKey();
            $this->addError($fallbackErrorKey, __('inventory.errors.submission_conflict'));

            return null;
        }

        $this->renewSubmissionKey();

        if ($movement === null) {
            $this->dispatch('notify', ['type' => 'info', 'message' => $unchangedMessage]);

            return null;
        }

        $this->dispatch('notify', [
            'type' => $movement->wasRecentlyCreated ? 'success' : 'info',
            'message' => $movement->wasRecentlyCreated
                ? __('inventory.messages.recorded')
                : __('inventory.messages.already_recorded'),
        ]);

        return $movement;
    }
}
