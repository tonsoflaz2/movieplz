<?php

namespace App\Support;

use App\Models\Invite;
use RuntimeException;

/**
 * 5-digit numeric invite codes. Generated on demand by the host
 * (either from inside the app or via `php artisan host:mint-code`)
 * and transferred out-of-band (phone, text, in person) to the friend
 * who will redeem it.
 *
 * 100,000 combinations combined with 10-minute expiry, single-use
 * semantics, and rate-limited redemption (5/min/IP) is safe against
 * brute force.
 */
class InviteCode
{
    public const LENGTH = 5;
    public const LIFETIME_MINUTES = 10;

    /**
     * Generate a zero-padded 5-digit string like "07392".
     * Retries on collision with an existing unredeemed/unexpired row.
     */
    public static function generate(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = str_pad(
                (string) random_int(0, 99999),
                self::LENGTH,
                '0',
                STR_PAD_LEFT
            );

            $conflict = Invite::query()
                ->where('code', $code)
                ->usable()
                ->exists();

            if (! $conflict) {
                return $code;
            }
        }

        throw new RuntimeException(
            'Could not generate a unique invite code after 5 attempts. '
            .'This should basically never happen.'
        );
    }

    /**
     * Strip whitespace from a user-entered code. The UI may allow
     * spaces between digits for readability ("7 3 9 2 5"), but the
     * stored value is always 5 contiguous digits.
     */
    public static function normalize(string $input): string
    {
        return preg_replace('/\s+/', '', trim($input)) ?? '';
    }

    /**
     * Cheap format check — does the string look like a valid code?
     * Used by client-side HAM pattern attribute and server-side
     * pre-validation before the DB lookup.
     */
    public static function looksValid(string $input): bool
    {
        return (bool) preg_match('/^\d{'.self::LENGTH.'}$/', self::normalize($input));
    }
}
