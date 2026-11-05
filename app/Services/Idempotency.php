<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Shared rules for server-issued idempotency keys and replay checks.
 *
 * A stored write may be returned for a retry only when the same actor sent the
 * same normalized payload; anything else is a conflict.
 */
final class Idempotency
{
    public const MAX_KEY_LENGTH = 64;

    /**
     * @throws InvalidArgumentException
     */
    public static function key(string $idempotencyKey): string
    {
        $key = Str::trim($idempotencyKey);

        if ($key === '' || strlen($key) > self::MAX_KEY_LENGTH) {
            throw new InvalidArgumentException('An idempotency key of 1 to 64 characters is required.');
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fingerprint(array $payload): string
    {
        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public static function matches(int|string|null $storedActorId, string $storedFingerprint, User $actor, string $fingerprint): bool
    {
        return (int) $storedActorId === (int) $actor->id && hash_equals($storedFingerprint, $fingerprint);
    }

    /**
     * @throws IdempotencyConflictException
     */
    public static function assertReplay(int|string|null $storedActorId, string $storedFingerprint, User $actor, string $fingerprint): void
    {
        if (! self::matches($storedActorId, $storedFingerprint, $actor, $fingerprint)) {
            throw new IdempotencyConflictException;
        }
    }
}
