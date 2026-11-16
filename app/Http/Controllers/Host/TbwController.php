<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use Illuminate\Http\Request;

/**
 * TBW, the host's list of everything friends have asked them to watch:
 * new suggestions, In progress, and titles added in the app through a
 * suggestion that aren't in the collection yet. Those can be edited,
 * kept ("Add to my collection") or removed from the library.
 */
class TbwController extends Controller
{
    /** GET /host/tbw.ham */
    public function index(Request $request)
    {
        $host = $this->host($request);
        $base = fn () => MediaRequest::query()->where('host_id', $host->host_id)->with('client')->orderByDesc('created_at');

        $kept = LibraryItem::query()->where('host_id', $host->host_id)
            ->where('origin', LibraryItem::ORIGIN_APP)->where('in_collection', true)
            ->whereNull('source_title')->orderBy('sort_title')->get();

        return response()
            ->view('ham.host.tbw', [
                'current_client' => $host,
                'new' => $base()->awaitingHost()->get(),
                'upNext' => $base()->upNext()->get(),
                'seen' => LibraryItem::query()->where('host_id', $host->host_id)->awaitingKeep()
                    ->with('suggestions.client')->orderByDesc('created_at')->get(),
                // Kept here, not yet in the linked sheet: lines to paste into it.
                'forSheet' => $host->host?->library_sheet_url ? $kept : collect(),
            ])
            ->header('Content-Type', 'text/html');
    }

    /** POST /host/library/{id}/keep — "Add to my collection". */
    public function keep(Request $request, int $id)
    {
        $item = $this->item($request, $id);
        $item->forceFill(['in_collection' => true])->save();

        return $this->row($item);
    }

    /**
     * POST /host/library/{id}/remove — takes a title added in the app off
     * the library. The suggestion keeps its "Seen" status.
     */
    public function remove(Request $request, int $id)
    {
        $item = $this->item($request, $id);
        abort_unless($item->isAwaitingKeep(), 404);
        $title = $item->title;
        $item->delete();

        return response()
            ->view('ham.host.partials._tbw_removed', ['id' => $id, 'title' => $title])
            ->header('Content-Type', 'text/html');
    }

    private function host(Request $request): Client
    {
        return $request->attributes->get('current_host');
    }

    private function item(Request $request, int $id): LibraryItem
    {
        return LibraryItem::query()->where('host_id', $this->host($request)->host_id)->findOrFail($id);
    }

    private function row(LibraryItem $item)
    {
        return response()
            ->view('ham.host.partials._tbw_item', ['item' => $item->load('suggestions.client')])
            ->header('Content-Type', 'text/html');
    }
}
