<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LibraryItem;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    /**
     * Titles per page of the library list. The next page is fetched while
     * PREFETCH_AHEAD rows of this one are still below the screen, so
     * scrolling doesn't stop at a page's end to wait for it.
     */
    public const PAGE = 40;

    public const PREFETCH_AHEAD = 15;

    /**
     * GET /library.ham — full library listing, partial.
     */
    public function index(Request $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_client');

        $type = $request->query('type');
        if (! in_array($type, ['film', 'series'], true)) {
            $type = null;
        }

        $query = LibraryItem::query()
            ->where('host_id', $client->host_id);

        if ($type) {
            $query->where('type', $type);
        }

        // id breaks ties (a remake shares its sort title), so no title is
        // shown twice or skipped across pages.
        $items = $query->orderBy('sort_title')->orderBy('id')->limit(self::PAGE)->get();

        return response()
            ->view('ham.client.library', [
                'current_client' => $client,
                'items' => $items,
                'query' => '',
                'type' => $type,
                'offset' => self::PAGE,
            ])
            ->header('Content-Type', 'text/html');
    }

    /**
     * GET /library/search.ham?q=... — small partial returned into
     * #library-results on the library screen when the search bar
     * changes.
     */
    public function search(Request $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_client');

        $q = trim((string) $request->query('q', ''));

        $type = $request->query('type');
        if (! in_array($type, ['film', 'series'], true)) {
            $type = null;
        }

        $query = LibraryItem::query()->where('host_id', $client->host_id);

        if ($type) {
            $query->where('type', $type);
        }

        if ($q !== '') {
            $query->search($q);
        }

        $offset = max(0, (int) $request->query('offset', 0));
        $items = $query->orderBy('sort_title')->orderBy('id')
            ->offset($offset)
            ->limit(self::PAGE)
            ->get();

        return response()
            ->view('ham.client._library_results', [
                'current_client' => $client,
                'items' => $items,
                'query' => $q,
                'type' => $type,
                'offset' => $offset + self::PAGE,
            ])
            ->header('Content-Type', 'text/html');
    }
}
