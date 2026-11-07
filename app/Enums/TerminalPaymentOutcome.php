<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the owner confirmed on the external terminal for a started payment with no approval.
 */
enum TerminalPaymentOutcome: string
{
    /** The terminal shows no charge for this payment (declined, abandoned, or never sent). */
    case NO_CHARGE = 'no_charge';

    /** The terminal charged the card and the charge was then voided on the terminal. */
    case VOIDED = 'voided';

    public function label(): string
    {
        return __('pos.terminal_outcomes.'.$this->value);
    }
}
