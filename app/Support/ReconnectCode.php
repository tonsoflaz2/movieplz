<?php

namespace App\Support;

/**
 * A person's reconnect code: twelve characters as XXXX-XXXX-XXXX, from an
 * alphabet without 0/O, 1/I/L, so it reads aloud and copies by hand. About
 * 59 bits, so unlike the short-lived 5-digit invite codes it can last:
 * it's how a host (or a friend) gets back to their library after
 * disconnecting a phone, or on a new phone.
 *
 * Stored twice on the client row: encrypted (shown again while they're
 * connected) and as an HMAC keyed by APP_KEY (looked up when typed in).
 */
class ReconnectCode
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    public const LENGTH = 12;

    public static function generate(): string
    {
        $raw = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $raw .= self::ALPHABET[random_int(0, $max)];
        }

        return self::format($raw);
    }

    /** "k7qp 4m2x-9htr" → "K7QP4M2X9HTR": case, spaces and dashes don't matter. */
    public static function normalize(string $input): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($input)) ?? '';
    }

    public static function format(string $code): string
    {
        return implode('-', str_split(self::normalize($code), 4));
    }

    public static function looksValid(string $input): bool
    {
        $code = self::normalize($input);

        return strlen($code) === self::LENGTH
            && strspn($code, self::ALPHABET) === self::LENGTH;
    }

    public static function hash(string $code): string
    {
        return hash_hmac('sha256', self::normalize($code), (string) config('app.key'));
    }
}
