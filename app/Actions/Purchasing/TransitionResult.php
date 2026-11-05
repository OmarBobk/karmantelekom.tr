<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Models\Purchase;
use App\Models\SupplierPayment;

/**
 * Outcome of a status change. $applied is false when an identical earlier request already made it.
 */
final readonly class TransitionResult
{
    public function __construct(
        public Purchase|SupplierPayment $model,
        public bool $applied,
    ) {}
}
