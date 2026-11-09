<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Stored status of a Store Network reservation.
 *
 * PENDING requests leave the device available. APPROVED reservations hold it until expires_at,
 * except while the device is in transit, which holds it until receipt. An approved reservation
 * past its expiry is effectively expired before anything stores EXPIRED; see
 * NetworkReservation::isActive(). REJECTED, CANCELLED, EXPIRED, and FULFILLED are final.
 */
enum ReservationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case FULFILLED = 'fulfilled';

    public function label(): string
    {
        return __('network.reservation_statuses.'.$this->value);
    }
}
