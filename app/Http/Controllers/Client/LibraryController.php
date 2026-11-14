<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LibraryItem;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
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

        $items = $query->orderBy('sort_title')->limit(20)->get();

        return response()
            ->view('ham.client.library', [
                'current_client' => $client,
                'items' => $items,
                'query' => '',
                'type' => $type,
                'offset' => 20,
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
        $items = $query->orderBy('sort_title')
            ->offset($offset)
            ->limit(20)
            ->get();

        return response()
            ->view('ham.client._library_results', [
                'current_client' => $client,
                'items' => $items,
                'query' => $q,
                'type' => $type,
                'offset' => $offset + 20,
            ])
            ->header('Content-Type', 'text/html');
    }
}
