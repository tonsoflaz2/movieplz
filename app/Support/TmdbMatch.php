<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Picks the right TMDB result for a library title. TMDB's search order
 * isn't "the one people mean": "Spirited Away" can rank a stage recording
 * first, and a series typed as a film finds a forgotten film of the same
 * name. So the results are scored: the same title (ignoring case, accents,
 * punctuation and a leading "The"), the year when one is given, the type
 * asked for, then how many people have rated it.
 */
class TmdbMatch
{
    /**
     * The best of the candidates, or null when there are none. Each
     * candidate is a TMDB search result plus 'media' ('movie' or 'tv').
     */
    public static function best(array $candidates, string $title, ?int $year, string $media): ?array
    {
        $best = null;
        $bestScore = null;
        foreach ($candidates as $candidate) {
            $score = self::score($candidate, $title, $year, $media);
            if ($bestScore === null || $score > $bestScore) {
                [$best, $bestScore] = [$candidate, $score];
            }
        }

        return $best;
    }

    /**
     * Whether a candidate is surely the title meant: the same title, and
     * the same year (give or take one) when a year was given. Once one is
     * found, no further searches are needed.
     */
    public static function confident(array $candidate, string $title, ?int $year): bool
    {
        if (! self::sameTitle($candidate, $title)) {
            return false;
        }
        $released = self::year($candidate);

        return $year === null || ($released !== null && abs($released - $year) <= 1);
    }

    public static function score(array $candidate, string $title, ?int $year, string $media): float
    {
        $score = self::sameTitle($candidate, $title) ? 100 : 0;

        if ($year !== null) {
            $released = self::year($candidate);
            $score += match (true) {
                $released === $year => 50,
                $released !== null && abs($released - $year) === 1 => 30,
                default => -20,
            };
        }

        if (($candidate['media'] ?? null) === $media) {
            $score += 25;
        }

        // 20,000 ratings is about +43, 20 ratings about +13.
        return $score + 10 * log10(1 + (int) ($candidate['vote_count'] ?? 0));
    }

    private static function sameTitle(array $candidate, string $title): bool
    {
        $wanted = self::normalize($title);
        foreach (['title', 'name', 'original_title', 'original_name'] as $key) {
            if (isset($candidate[$key]) && self::normalize($candidate[$key]) === $wanted) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $title): string
    {
        $title = Str::lower(Str::ascii($title));
        $title = str_replace('&', 'and', $title);
        $title = trim(preg_replace('/[^a-z0-9]+/', ' ', $title));

        return preg_replace('/^(the|a|an) /', '', $title);
    }

    private static function year(array $candidate): ?int
    {
        $date = $candidate['release_date'] ?? $candidate['first_air_date'] ?? '';

        return preg_match('/^(\d{4})/', $date, $m) ? (int) $m[1] : null;
    }
}
