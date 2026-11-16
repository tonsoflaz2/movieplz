<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\LibrarySource;
use Illuminate\Http\Request;

/**
 * The host's Library source screen: link a public Google Sheet (synced
 * now and every few minutes after), sync it on demand, turn mirror mode
 * on or off, or paste titles. Every action answers with the whole screen,
 * swapped over #library-source.
 */
class LibrarySourceController extends Controller
{
    /**
     * GET /host/library-source.ham
     */
    public function show(Request $request)
    {
        return $this->screen($request);
    }

    /**
     * POST /host/library-source — save the sheet link and sync it.
     * An empty link unlinks the sheet (titles already added stay).
     */
    public function link(Request $request)
    {
        $host = $this->host($request);
        $link = trim((string) $request->input('sheet_url', ''));

        if ($link === '') {
            $host->forceFill(['library_sheet_url' => null])->save();
            return $this->screen($request, notice: 'Sheet unlinked. Your titles stay in the library.');
        }

        if (! LibrarySource::csvUrl($link)) {
            return $this->screen($request, error: "That isn't a Google Sheets link. Copy it from the sheet's Share button.", link: $link);
        }

        $previous = $host->library_sheet_url;
        $host->forceFill(['library_sheet_url' => $link])->save();
        $new = LibrarySource::sync($host);

        if ($new === null) {
            $host->forceFill(['library_sheet_url' => $previous])->save();
            return $this->screen($request,
                error: "Couldn't read that sheet. In Google Sheets, Share → General access → \"Anyone with the link\", then try again.",
                link: $link);
        }

        LibrarySource::enrichSoon($host);

        return $this->screen($request, notice: $this->added($new));
    }

    /**
     * POST /host/library-sync — sync the linked sheet now. ?force=1 is
     * "Remove them anyway": removals the mirror guard held back go ahead.
     */
    public function sync(Request $request)
    {
        $host = $this->host($request);
        $report = LibrarySource::syncReport($host, force: $request->query('force') === '1');
        if ($report['new'] ?? 0) {
            LibrarySource::enrichSoon($host);
        }

        return $report === null
            ? $this->screen($request, error: "Couldn't read your sheet. Check it's still shared with \"Anyone with the link\".")
            : $this->screen($request, notice: $this->synced($report));
    }

    /**
     * POST /host/library-mirror?on=1|0 — "My sheet is my whole library" on
     * or off. Turning it on syncs straight away, so titles the sheet no
     * longer lists leave now.
     */
    public function mirror(Request $request)
    {
        $host = $this->host($request);
        $on = $request->query('on') === '1';
        $host->forceFill(['library_mirror' => $on] + ($on ? [] : ['library_mirror_held' => null]))->save();

        if (! $on) {
            return $this->screen($request, notice: 'Mirror mode is off. Titles stay when your sheet drops them.');
        }
        if (! $host->library_sheet_url) {
            return $this->screen($request, notice: 'Mirror mode is on. It starts once you link a sheet.');
        }

        $report = LibrarySource::syncReport($host);
        if ($report === null) {
            return $this->screen($request, error: "Mirror mode is on, but your sheet couldn't be read just now. Check it's still shared with \"Anyone with the link\".");
        }
        if ($report['new']) {
            LibrarySource::enrichSoon($host);
        }

        return $this->screen($request, notice: 'Mirror mode is on. '.$this->synced($report));
    }

    /**
     * POST /host/library-paste — add pasted titles, one per line.
     */
    public function paste(Request $request)
    {
        $text = (string) $request->input('titles', '');
        $rows = LibrarySource::parsePasted($text);

        if (! $rows) {
            return $this->screen($request, error: 'Paste at least one title, one per line.');
        }

        $host = $this->host($request);
        $new = LibrarySource::import($host, $rows, \App\Models\LibraryItem::ORIGIN_PASTE);
        LibrarySource::enrichSoon($host);

        return $this->screen($request, notice: $this->added($new));
    }

    private function host(Request $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_client');

        return $client->host;
    }

    /** @param array{new: int, removed: int, held: int} $report */
    private function synced(array $report): string
    {
        $parts = [$report['new'] === 1 ? '1 new title' : "{$report['new']} new titles"];
        if ($report['removed']) {
            $parts[] = $report['removed'] === 1 ? '1 removed' : "{$report['removed']} removed";
        }
        if (! $report['removed'] && ! $report['new']) {
            return 'Up to date: no changes.';
        }

        return 'Synced: '.implode(', ', $parts).'.';
    }

    private function added(int $new): string
    {
        return match ($new) {
            0 => 'Up to date: no new titles.',
            1 => 'Added 1 new title.',
            default => "Added {$new} new titles.",
        };
    }

    private function screen(Request $request, ?string $notice = null, ?string $error = null, ?string $link = null)
    {
        $host = $this->host($request)->fresh();

        return response()
            ->view('ham.host.library_source', [
                'host' => $host,
                'total' => $host->libraryItems()->count(),
                'notice' => $notice,
                'error' => $error,
                'link' => $link ?? $host->library_sheet_url,
            ])
            ->header('Content-Type', 'text/html');
    }
}
