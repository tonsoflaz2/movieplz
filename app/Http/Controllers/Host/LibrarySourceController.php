<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\LibrarySource;
use Illuminate\Http\Request;

/**
 * The host's Library source screen: link a public Google Sheet (synced
 * now and every few minutes after), sync it on demand, or paste titles.
 * Every action answers with the whole screen, swapped over #library-source.
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
     * POST /host/library-sync — sync the linked sheet now.
     */
    public function sync(Request $request)
    {
        $host = $this->host($request);
        $new = LibrarySource::sync($host);
        if ($new) {
            LibrarySource::enrichSoon($host);
        }

        return $new === null
            ? $this->screen($request, error: "Couldn't read your sheet. Check it's still shared with \"Anyone with the link\".")
            : $this->screen($request, notice: $this->added($new));
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
