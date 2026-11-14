<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The likeliest TMDB matches for a title, for the host to pick from after
 * editing it: its own type with the year, then without, then the other
 * type, scored by TmdbMatch, best first.
 */
class TmdbSearch
{
    /**
     * Up to [limit] candidates as ['tmdb_id', 'media', 'title', 'year',
     * 'poster_url', 'overview'], best first. Empty when TMDB has nothing;
     * null when TMDB can't be asked (no token, unreachable, an error).
     */
    public static function candidates(string $title, ?int $year, string $type, int $limit = 5): ?array
    {
        $token = config('tmdb.read_token');
        if (! $token) {
            return null;
        }

        $first = $type === 'series' ? 'tv' : 'movie';
        $tries = array_merge(
            $year ? [[$first, $year]] : [],
            [[$first, null], [$first === 'tv' ? 'movie' : 'tv', null]],
        );

        $found = [];
        try {
            foreach ($tries as [$media, $tryYear]) {
                $params = ['query' => $title, 'include_adult' => false, 'language' => 'en-US', 'page' => 1];
                if ($tryYear) {
                    $params[$media === 'tv' ? 'first_air_date_year' : 'year'] = $tryYear;
                }
                $response = Http::withToken($token)->timeout(10)
                    ->get("https://api.themoviedb.org/3/search/{$media}", $params);
                if (! $response->ok()) {
                    Log::warning('TMDB search failed', ['title' => $title, 'status' => $response->status()]);

                    return null;
                }
                foreach ($response->json('results') ?? [] as $result) {
                    if (isset($result['id'])) {
                        $found["{$media}:{$result['id']}"] = $result + ['media' => $media];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TMDB search error', ['title' => $title, 'error' => $e->getMessage()]);

            return null;
        }

        $scored = array_values($found);
        usort($scored, fn ($a, $b) => TmdbMatch::score($b, $title, $year, $first) <=> TmdbMatch::score($a, $title, $year, $first));

        return array_map(function (array $c) {
            $date = $c['release_date'] ?? $c['first_air_date'] ?? '';

            return [
                'tmdb_id' => (string) $c['id'],
                'media' => $c['media'],
                'title' => (string) ($c['title'] ?? $c['name'] ?? ''),
                'year' => preg_match('/^(\d{4})/', (string) $date, $m) ? (int) $m[1] : null,
                'poster_url' => ! empty($c['poster_path']) ? config('tmdb.image_base').$c['poster_path'] : null,
                'overview' => $c['overview'] ?? null,
            ];
        }, array_slice($scored, 0, $limit));
    }
}
