<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Models\CheckoutAttempt;

/**
 * Result of StartTerminalPayment. Only a new start may be followed by an instruction to charge
 * the card; a replayed start may already have been charged, so the terminal must be checked.
 */
final readonly class TerminalPaymentStart
{
    public function __construct(
        public CheckoutAttempt $attempt,
        public bool $isNewStart,
    ) {}

    public function paymentKey(): string
    {
        return (string) $this->attempt->terminal_payment_key;
    }
}
