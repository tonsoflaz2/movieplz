<?php

namespace App\Support;

use App\Models\LibraryItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A library title's TMDB entry from the IDs its Google Sheet gives, tried
 * in order: the TMDB ID, then the IMDb ID, then the TVDB ID. IMDb and TVDB
 * IDs are looked up with TMDB's own /find, so MoviePlz only ever talks to
 * TMDB. The first ID that finds a title wins; one that finds nothing (a
 * typo, or a TMDB ID of the other type) passes to the next.
 */
class TmdbIds
{
    /**
     * ['tmdb_id', 'poster_url', 'overview', 'matched_by'] from the first ID
     * that works; [] when none does (search by name instead); null when
     * TMDB couldn't be asked (try again later).
     */
    public static function lookup(LibraryItem $item, string $token): ?array
    {
        $media = $item->type === 'series' ? 'tv' : 'movie';
        $tries = array_filter([
            'tmdb_id' => $item->source_tmdb_id,
            'imdb_id' => $item->source_imdb_id,
            'tvdb_id' => $item->source_tvdb_id,
        ]);

        try {
            foreach ($tries as $source => $id) {
                $found = $source === 'tmdb_id'
                    ? self::byTmdbId($id, $media, $token)
                    : self::byExternalId($id, $source, $media, $token);
                if ($found === null) {
                    return null;
                }
                if ($found) {
                    return self::details($found) + ['matched_by' => $source];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("TMDB ID lookup error for {$item->title}: {$e->getMessage()}");

            return null;
        }

        return [];
    }

    /**
     * The title with that TMDB ID, in the item's own type only: the same
     * number is a different title in the other type. [] when there's none.
     */
    private static function byTmdbId(string $id, string $media, string $token): ?array
    {
        $response = Http::withToken($token)->timeout(10)
            ->get("https://api.themoviedb.org/3/{$media}/{$id}", ['language' => 'en-US']);
        if ($response->status() === 404) {
            return [];
        }
        if (! $response->ok()) {
            Log::warning('TMDB lookup by ID failed', ['id' => $id, 'status' => $response->status()]);

            return null;
        }

        return $response->json() ?? [];
    }

    /** The title TMDB knows by that IMDb or TVDB ID, its own type first. [] when there's none. */
    private static function byExternalId(string $id, string $source, string $media, string $token): ?array
    {
        $response = Http::withToken($token)->timeout(10)
            ->get("https://api.themoviedb.org/3/find/{$id}", ['external_source' => $source, 'language' => 'en-US']);
        if ($response->status() === 404) {
            return [];
        }
        if (! $response->ok()) {
            Log::warning('TMDB find failed', ['id' => $id, 'source' => $source, 'status' => $response->status()]);

            return null;
        }

        $own = $media === 'tv' ? 'tv_results' : 'movie_results';
        $other = $media === 'tv' ? 'movie_results' : 'tv_results';

        return $response->json("{$own}.0") ?? $response->json("{$other}.0") ?? [];
    }

    /** @return array{tmdb_id: string, poster_url: ?string, overview: ?string} */
    private static function details(array $result): array
    {
        return [
            'tmdb_id' => (string) $result['id'],
            'poster_url' => ! empty($result['poster_path']) ? config('tmdb.image_base').$result['poster_path'] : null,
            'overview' => ($result['overview'] ?? '') !== '' ? $result['overview'] : null,
        ];
    }
}
