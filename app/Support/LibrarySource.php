<?php

namespace App\Support;

use App\Models\Host;
use App\Models\LibraryItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Where a host's library comes from: a public Google Sheet (synced on a
 * schedule and on demand) or titles pasted in by hand.
 *
 * In mirror mode (hosts.library_mirror) the sheet is the whole library: a
 * sync also removes the sheet's titles that the sheet no longer lists.
 * Titles added in the app (through a suggestion) or pasted always stay.
 *
 * A sheet needs a Title column; Type (film/series) and Year are optional.
 * Columns are found by their header, so any order works. Without a
 * recognisable header the first column is the title.
 */
class LibrarySource
{
    /**
     * A mirror sync holds back its removals when the sheet comes back empty,
     * or would lose more than half of its titles once it has at least this
     * many: more likely a bad read or a wrong tab than a real clear-out. The
     * host can remove them anyway from the Add titles screen.
     */
    public const MIRROR_GUARD_MIN = 20;

    /**
     * The CSV export URL for a Google Sheets link, or null if the link
     * isn't a Google Sheet. Accepts the share link (…/d/{id}/edit#gid=N)
     * or an export link as-is.
     */
    public static function csvUrl(string $link): ?string
    {
        if (! preg_match('#docs\.google\.com/spreadsheets/d/([A-Za-z0-9_-]+)#', $link, $m)) {
            return null;
        }

        $gid = preg_match('/[#&?]gid=(\d+)/', $link, $g) ? $g[1] : '0';

        return "https://docs.google.com/spreadsheets/d/{$m[1]}/export?format=csv&gid={$gid}";
    }

    /**
     * Fetch and parse a sheet. Null when it can't be read (private sheet,
     * bad link, network), with the reason logged.
     */
    public static function fetchRows(string $link): ?array
    {
        $url = self::csvUrl($link);
        if (! $url) {
            return null;
        }

        try {
            $response = Http::timeout(15)->get($url);
            // A private sheet answers with Google's sign-in page, not CSV.
            if (! $response->ok() || str_contains((string) $response->header('Content-Type'), 'text/html')) {
                Log::warning('Library sheet unreadable', ['status' => $response->status(), 'url' => $url]);
                return null;
            }

            return self::parseCsv($response->body());
        } catch (\Throwable $e) {
            Log::warning('Library sheet fetch failed: '.$e->getMessage());
            return null;
        }
    }

    public static function parseCsv(string $csv): array
    {
        $lines = array_values(array_filter(str_getcsv($csv, "\n"), fn ($l) => trim($l) !== ''));
        if (! $lines) {
            return [];
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv($lines[0]));
        $titleCol = array_search('title', $header, true);
        $typeCol = array_search('type', $header, true);
        $yearCol = array_search('year', $header, true);
        if ($titleCol === false) {
            $titleCol = 0;
        } else {
            array_shift($lines);
        }

        $rows = [];
        foreach ($lines as $line) {
            $cols = str_getcsv($line);
            $row = self::row(
                $cols[$titleCol] ?? '',
                $typeCol === false ? null : ($cols[$typeCol] ?? null),
                $yearCol === false ? null : ($cols[$yearCol] ?? null),
            );
            if ($row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * One title per line, as people type them: "Dune (2021)",
     * "The Bear, series", "Heat 1995".
     */
    public static function parsePasted(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // A trailing year and a trailing "series"/"film", in either order.
            $type = $year = null;
            for ($i = 0; $i < 2; $i++) {
                if ($type === null && preg_match('/[,\-–(]\s*(series|show|tv|film|movie)\s*\)?\s*$/i', $line, $m)) {
                    $type = $m[1];
                    $line = trim(substr($line, 0, -strlen($m[0])));
                }
                if ($year === null && preg_match('/[\s,(]\(?((?:19|20)\d{2})\)?\s*$/', $line, $m)) {
                    $year = $m[1];
                    $line = rtrim(substr($line, 0, -strlen($m[0])), " ,(");
                }
            }

            if ($row = self::row($line, $type, $year)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Add or update the rows in the host's collection ([origin]: sheet or
     * paste). Returns how many titles were new. Nothing is removed here;
     * see removeMissing() for mirror mode.
     *
     * A row is matched by the sheet's own spelling (source_title/year), so a
     * title the host renamed in the app keeps its new name and isn't added
     * again; then by its current name, so a title added in the app (TBW)
     * that the sheet now lists joins the collection. added_at is set once,
     * when the title first arrives.
     */
    public static function import(Host $host, array $rows, string $origin = LibraryItem::ORIGIN_SHEET): int
    {
        $now = now();
        $new = 0;
        foreach ($rows as $row) {
            $item = LibraryItem::query()->where('host_id', $host->id)
                ->where('source_title', $row['title'])->where('source_year', $row['year'])->first()
                ?? LibraryItem::query()->where('host_id', $host->id)
                    ->where('title', $row['title'])->where('year', $row['year'])->first();

            if ($item) {
                $changes = ['in_collection' => true];
                if ($item->source_title === null) {
                    $changes += ['source_title' => $row['title'], 'source_year' => $row['year']];
                }
                // The sheet's type, unless the host has set it in the app.
                if (! $item->edited_at) {
                    $changes += ['sort_title' => $row['sort_title'], 'type' => $row['type']];
                }
                $item->forceFill($changes)->save();
                continue;
            }

            $item = LibraryItem::create([
                'host_id' => $host->id,
                'title' => $row['title'],
                'year' => $row['year'],
                'sort_title' => $row['sort_title'],
                'type' => $row['type'],
                'added_at' => $now,
                'origin' => $origin,
                'in_collection' => true,
                'source_title' => $row['title'],
                'source_year' => $row['year'],
            ]);
            $new++;
            // Posters and overviews at once for titles another library
            // already has; the rest wait for tmdb:enrich.
            if ($known = LibraryItem::knownDetails($row['title'], $row['year'], $row['type'])) {
                $item->forceFill($known)->save();
            }
        }

        return $new;
    }

    /**
     * Fetch posters and overviews for the host's new titles right after
     * this response goes out, instead of waiting for the schedule.
     */
    public static function enrichSoon(Host $host): void
    {
        $id = $host->id;
        dispatch(fn () => Artisan::call('tmdb:enrich', ['--host-id' => $id, '--limit' => 300]))->afterResponse();
    }

    /**
     * Sync a host from its sheet. Returns how many titles were new; null
     * when the sheet can't be read.
     */
    public static function sync(Host $host): ?int
    {
        return self::syncReport($host)['new'] ?? null;
    }

    /**
     * Sync a host from its sheet: new titles, and in mirror mode the removal
     * of titles the sheet dropped. [force] removes them even when the guard
     * would hold them back. Null when the sheet can't be read.
     *
     * @return array{new: int, removed: int, held: int}|null
     */
    public static function syncReport(Host $host, bool $force = false): ?array
    {
        if (! $host->library_sheet_url) {
            return null;
        }

        $rows = self::fetchRows($host->library_sheet_url);
        if ($rows === null) {
            return null;
        }

        $report = ['new' => self::import($host, $rows), 'removed' => 0, 'held' => 0];
        if ($host->library_mirror) {
            [$report['removed'], $report['held']] = self::removeMissing($host, $rows, $force);
        }
        $host->forceFill([
            'library_synced_at' => now(),
            'library_mirror_held' => $report['held'] ?: null,
        ])->save();

        return $report;
    }

    /**
     * Mirror mode: remove the host's sheet titles that [rows] (the sheet as
     * just read) no longer lists, matched by the sheet's own spelling like
     * import(). Titles added in the app or pasted are never touched. A
     * suggestion that had added one keeps its "Added" status.
     *
     * @return array{0: int, 1: int} [removed, held back by the guard]
     */
    public static function removeMissing(Host $host, array $rows, bool $force = false): array
    {
        $listed = [];
        foreach ($rows as $row) {
            $listed[$row['title']."\0".$row['year']] = true;
        }

        $sheetItems = LibraryItem::query()->where('host_id', $host->id)
            ->where('origin', LibraryItem::ORIGIN_SHEET)
            ->get(['id', 'source_title', 'source_year']);
        $missing = $sheetItems->reject(fn ($i) => isset($listed[$i->source_title."\0".$i->source_year]));

        if ($missing->isEmpty()) {
            return [0, 0];
        }

        $suspicious = $rows === []
            || ($sheetItems->count() >= self::MIRROR_GUARD_MIN && $missing->count() * 2 > $sheetItems->count());
        if ($suspicious && ! $force) {
            Log::warning('Library mirror held back removals', [
                'host' => $host->id, 'missing' => $missing->count(), 'sheet_titles' => $sheetItems->count(), 'rows' => count($rows),
            ]);

            return [0, $missing->count()];
        }

        LibraryItem::query()->whereIn('id', $missing->pluck('id'))->delete();

        return [$missing->count(), 0];
    }

    private static function row(string $title, ?string $type, ?string $year): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $type = strtolower(trim((string) $type));
        $type = in_array($type, ['series', 'show', 'tv'], true) ? 'series' : 'film';

        return [
            'title' => mb_substr($title, 0, 200),
            'sort_title' => SortTitle::from($title),
            'type' => $type,
            'year' => (int) $year ?: null,
        ];
    }
}
