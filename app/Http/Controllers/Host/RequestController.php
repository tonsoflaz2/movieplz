<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use App\Services\PushService;
use App\Support\LibrarySource;
use App\Support\SortTitle;
use Illuminate\Http\Request;

/**
 * The host's suggestions inbox. Friends suggest things to watch; the host
 * answers "I'll watch it" (Up next), "Saw it!" (the title goes on their
 * list, the library) or "Not for me" (Passed).
 */
class RequestController extends Controller
{
    /** ?status= values: the tabs of the inbox. */
    private const FILTERS = ['new', 'up_next', 'seen', 'passed'];

    /**
     * GET /host/requests.ham — inbox, partial into #main.
     */
    public function index(Request $request)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');

        $filter = in_array($request->query('status'), self::FILTERS, true) ? $request->query('status') : 'new';

        $query = MediaRequest::query()->where('host_id', $host->host_id);
        match ($filter) {
            'new' => $query->awaitingHost(),
            'up_next' => $query->upNext(),
            'seen' => $query->where('status', MediaRequest::STATUS_FULFILLED),
            'passed' => $query->where('status', MediaRequest::STATUS_DECLINED),
        };

        return response()
            ->view('ham.host.requests', [
                'current_client' => $host,
                'filter' => $filter,
                'requests' => $query->with('client')->orderByDesc('created_at')->get(),
            ])
            ->header('Content-Type', 'text/html');
    }

    /**
     * POST /host/requests/{id}/up-next — "I'll watch it".
     */
    public function upNext(Request $request, int $id)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');
        $suggestion = $this->find($host, $id);

        if ($suggestion->status === MediaRequest::STATUS_PENDING && ! $suggestion->up_next_at) {
            $suggestion->forceFill(['up_next_at' => now()])->save();
            $this->tell($suggestion, 'up-for-it', "{$host->display_name}'s up for it", "{$suggestion->title} is on their Up next list.");
        }

        return $this->row($suggestion);
    }

    /**
     * POST /host/requests/{id}/fulfill — "Saw it!": the title goes on the
     * host's list (the library), with its poster and details.
     */
    public function fulfill(Request $request, int $id)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');
        $suggestion = $this->find($host, $id);

        if ($suggestion->status !== MediaRequest::STATUS_FULFILLED) {
            $item = $this->addToList($suggestion);
            $suggestion->forceFill([
                'status' => MediaRequest::STATUS_FULFILLED,
                'fulfilled_at' => now(),
                'fulfilled_library_item_id' => $item->id,
            ])->save();
            LibrarySource::enrichSoon($host->host);

            $this->tell($suggestion, 'saw-it', "{$host->display_name} saw it!", "{$suggestion->title} is on their list now.");
        }

        return $this->row($suggestion);
    }

    /**
     * POST /host/requests/{id}/decline — "Not for me". No notification:
     * the friend sees "Passed" in their list.
     */
    public function decline(Request $request, int $id)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');
        $suggestion = $this->find($host, $id);

        if ($suggestion->status === MediaRequest::STATUS_PENDING) {
            $suggestion->forceFill(['status' => MediaRequest::STATUS_DECLINED])->save();
        }

        return $this->row($suggestion);
    }

    private function find(Client $host, int $id): MediaRequest
    {
        return MediaRequest::query()->where('host_id', $host->host_id)->findOrFail($id);
    }

    /** The library title for a seen suggestion: the one already there, or a new one. */
    private function addToList(MediaRequest $suggestion): LibraryItem
    {
        $type = $suggestion->type ?: 'film';
        // A new title waits in TBW ("Seen, not in your collection yet")
        // until the host keeps it; one already there is left as it is.
        $item = LibraryItem::query()->firstOrCreate(
            ['host_id' => $suggestion->host_id, 'title' => $suggestion->title, 'year' => $suggestion->year],
            ['sort_title' => SortTitle::from($suggestion->title), 'type' => $type, 'added_at' => now(),
                'origin' => LibraryItem::ORIGIN_APP, 'in_collection' => false],
        );
        if ($item->wasRecentlyCreated && $known = LibraryItem::knownDetails($item->title, $item->year, $item->type)) {
            $item->forceFill($known)->save();
        }

        return $item;
    }

    /** The friend who suggested it: always the suggestion's own client, never the host. */
    private function tell(MediaRequest $suggestion, string $event, string $title, string $body): void
    {
        PushService::notify(Client::find($suggestion->client_id), $event, $title, $body, ['route' => '/requests.ham']);
    }

    private function row(MediaRequest $suggestion)
    {
        return response()
            ->view('ham.host.partials._request_row', ['request' => $suggestion->load('client')])
            ->header('Content-Type', 'text/html');
    }
}
