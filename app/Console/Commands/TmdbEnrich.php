<?php

namespace App\Console\Commands;

use App\Models\LibraryItem;
use App\Support\TmdbMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TmdbEnrich extends Command
{
    protected $signature = 'tmdb:enrich {--limit=50 : Max items to enrich per run} {--host-id= : Only this host\'s library} {--force : Re-enrich items that already have a poster}';
    protected $description = 'Enrich library items with TMDB poster URLs and overviews';

    public function handle(): int
    {
        $token = config('tmdb.read_token');
        if (! $token) {
            $this->error('TMDB_READ not set in .env');
            return 1;
        }

        $query = LibraryItem::query()
            ->orderBy('id')
            ->when($this->option('host-id'), fn ($q, $id) => $q->where('host_id', $id));
        if (! $this->option('force')) {
            $query->whereNull('tmdb_checked_at');
        }
        $items = $query->limit((int) $this->option('limit'))->get();

        if ($items->isEmpty()) {
            $this->info('Nothing to enrich.');
            return 0;
        }

        $enriched = 0;
        $reused = 0;
        $unmatched = 0;

        foreach ($items as $item) {
            // Another library already has this title: no TMDB call.
            if (! $this->option('force') && $known = LibraryItem::knownDetails($item->title, $item->year, $item->type)) {
                $item->forceFill($known)->save();
                $reused++;
                continue;
            }

            $result = $this->lookup($item, $token);
            if ($result === null) {
                continue; // TMDB unreachable or erroring: try again next run
            }

            // A title TMDB doesn't know is marked checked too, so it isn't
            // retried ahead of new titles every run.
            $item->forceFill(($result ?: []) + ['tmdb_checked_at' => now()])->save();
            if ($result) {
                $enriched++;
                $this->line("  ✓ {$item->title} ({$item->year})");
            } else {
                $unmatched++;
                $this->line("  ✗ {$item->title} ({$item->year})");
            }
            usleep(250_000); // 4 req/sec, well under TMDB rate limit
        }

        $this->info("Enriched {$enriched}, reused {$reused}, no match {$unmatched}.");
        return 0;
    }

    /**
     * The TMDB details for a title, [] when TMDB has no match, or null
     * when TMDB couldn't be asked.
     */
    private function lookup(LibraryItem $item, string $token): ?array
    {
        try {
            $first = $item->type === 'series' ? 'tv' : 'movie';
            // Its own type with the year, then without; then the other type
            // (sheets often call a series a film). The results of every try
            // so far are scored together (TmdbMatch), stopping once one is
            // surely the title meant.
            $tries = array_merge(
                $item->year ? [[$first, $item->year]] : [],
                [[$first, null], [$first === 'tv' ? 'movie' : 'tv', null]],
            );
            $candidates = [];

            foreach ($tries as [$type, $year]) {
                $params = ['query' => $item->title, 'include_adult' => false, 'language' => 'en-US', 'page' => 1];
                if ($year) {
                    $params[$type === 'tv' ? 'first_air_date_year' : 'year'] = $year;
                }

                $response = Http::withToken($token)->timeout(10)
                    ->get("https://api.themoviedb.org/3/search/{$type}", $params);

                if (! $response->ok()) {
                    Log::warning("TMDB search failed for {$item->title}", ['status' => $response->status()]);
                    return null;
                }

                foreach ($response->json('results') ?? [] as $result) {
                    $candidates[] = $result + ['media' => $type];
                }
                $best = TmdbMatch::best($candidates, $item->title, $item->year, $first);
                if ($best && TmdbMatch::confident($best, $item->title, $item->year)) {
                    break;
                }
            }

            $match = TmdbMatch::best($candidates, $item->title, $item->year, $first);
            if (! $match) {
                return [];
            }
            $posterPath = $match['poster_path'] ?? null;

            return [
                'tmdb_id' => (string) $match['id'],
                'poster_url' => $posterPath ? config('tmdb.image_base').$posterPath : null,
                'overview' => $match['overview'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning("TMDB lookup error for {$item->title}: {$e->getMessage()}");
            return null;
        }
    }
}
