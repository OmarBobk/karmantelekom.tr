<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Models\CardTerminal;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveCardTerminal
{
    /**
     * Register an external card terminal for a store, or change its active flag.
     *
     * Merchant and terminal numbers are stored as printed (spaces removed, upper case) and are
     * unique together, because approval references are only unique within one terminal.
     *
     * @param  array{label?: ?string, merchant_number?: ?string, terminal_number?: ?string}  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(User $actor, Store $store, array $input): CardTerminal
    {
        Gate::forUser($actor)->authorize('manageCardTerminals', $store);

        $attributes = Validator::make([
            'label' => Str::trim((string) ($input['label'] ?? '')),
            'merchant_number' => self::normalizeNumber($input['merchant_number'] ?? null),
            'terminal_number' => self::normalizeNumber($input['terminal_number'] ?? null),
        ], [
            'label' => ['required', 'string', 'max:100'],
            'merchant_number' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9\-]+$/'],
            'terminal_number' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9\-]+$/'],
        ], is_array($messages = __('operations.validation')) ? $messages : [], [
            'label' => __('pos.terminals.fields.label'),
            'merchant_number' => __('pos.terminals.fields.merchant_number'),
            'terminal_number' => __('pos.terminals.fields.terminal_number'),
        ])->validate();

        try {
            return CardTerminal::query()->create([...$attributes, 'store_id' => $store->id, 'is_active' => true]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['terminal_number' => __('pos.terminals.errors.duplicate')]);
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function setActive(User $actor, Store $store, int $terminalId, bool $active): CardTerminal
    {
        Gate::forUser($actor)->authorize('manageCardTerminals', $store);

        $terminal = $store->cardTerminals()->whereKey($terminalId)->firstOrFail();
        $terminal->update(['is_active' => $active]);

        return $terminal;
    }

    public static function normalizeNumber(?string $value): string
    {
        return Str::upper((string) preg_replace('/\s+/u', '', Str::trim((string) $value)));
    }
}
