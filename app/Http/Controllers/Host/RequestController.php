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
 * The host's suggestions inbox. Friends suggest titles; the host answers
 * "In progress", "Added" (the title goes in the library unless "Add to
 * library?" is unticked) or "Declined", and can write a reply the friend
 * sees.
 */
class RequestController extends Controller
{
    /** ?status= values: the tabs of the inbox. */
    private const FILTERS = ['new', 'up_next', 'seen', 'passed'];

    /** The longest reply a host can send. */
    public const REPLY_MAX = 500;

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
     * POST /host/requests/{id}/up-next — "In progress".
     */
    public function upNext(Request $request, int $id)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');
        $suggestion = $this->find($host, $id);
        [$reply, $error] = $this->reply($request);
        if ($error) {
            return $this->row($suggestion, $error);
        }

        if ($suggestion->status === MediaRequest::STATUS_PENDING && ! $suggestion->up_next_at) {
            $suggestion->forceFill(['up_next_at' => now()] + $this->replyFields($reply))->save();
            $this->tell($suggestion, 'up-for-it', "{$host->display_name} is working on {$suggestion->title}", $reply ?? '');
        }

        return $this->row($suggestion);
    }

    /**
     * POST /host/requests/{id}/fulfill — "Added": the title goes in the
     * host's library, with its poster and details, unless "Add to library?"
     * was unticked; then only the suggestion is marked Added.
     */
    public function fulfill(Request $request, int $id)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');
        $suggestion = $this->find($host, $id);
        [$reply, $error] = $this->reply($request);
        if ($error) {
            return $this->row($suggestion, $error);
        }
        $add = $this->addToLibrary($request);

        if ($suggestion->status !== MediaRequest::STATUS_FULFILLED) {
            $item = $add ? $this->addToList($suggestion) : null;
            $suggestion->forceFill([
                'status' => MediaRequest::STATUS_FULFILLED,
                'fulfilled_at' => now(),
                'fulfilled_library_item_id' => $item?->id,
            ] + $this->replyFields($reply))->save();

            if ($item) {
                LibrarySource::enrichSoon($host->host);
            }
            // Just "Christian added Dune", or the reply under it when there is one.
            $this->tell($suggestion, $item ? 'saw-it' : 'added', "{$host->display_name} added {$suggestion->title}", $reply ?? '');
        }

        return $this->row($suggestion);
    }

    /**
     * POST /host/requests/{id}/decline — "Declined". The friend sees
     * "Declined" in their list; they're notified only when the host wrote
     * a reply.
     */
    public function decline(Request $request, int $id)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');
        $suggestion = $this->find($host, $id);
        [$reply, $error] = $this->reply($request);
        if ($error) {
            return $this->row($suggestion, $error);
        }

        if ($suggestion->status === MediaRequest::STATUS_PENDING) {
            $suggestion->forceFill(['status' => MediaRequest::STATUS_DECLINED] + $this->replyFields($reply))->save();
            if ($reply !== null) {
                $this->tell($suggestion, 'declined', "{$host->display_name} declined {$suggestion->title}", $reply);
            }
        }

        return $this->row($suggestion);
    }

    /**
     * Whether "Add to library?" was ticked. The row's form always sends
     * add_sent=1 beside the checkbox, so an unticked box is noticed whether
     * the app sends it as a false value or leaves it out. A post without
     * that form (none today) adds, as "Added" always did.
     */
    private function addToLibrary(Request $request): bool
    {
        if ($request->has('add')) {
            return ! in_array(strtolower(trim((string) $request->input('add'))), ['false', '0', 'off', 'no', ''], true);
        }

        return ! $request->filled('add_sent');
    }

    /**
     * The reply typed under the buttons: [text or null when empty, error or null].
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function reply(Request $request): array
    {
        $reply = trim((string) $request->input('reply', ''));
        if (mb_strlen($reply) > self::REPLY_MAX) {
            return [null, 'Keep the reply to '.self::REPLY_MAX.' characters or fewer.'];
        }

        return [$reply === '' ? null : $reply, null];
    }

    /** An empty reply keeps the one sent before, if any. */
    private function replyFields(?string $reply): array
    {
        return $reply === null ? [] : ['host_reply' => $reply];
    }

    /**
     * POST /host/requests/{id}/delete — a suggestion gone for good, whatever
     * its status (a duplicate, sent by mistake). It leaves the friend's list
     * too, without a notification. A title it had added stays in the
     * library.
     */
    public function destroy(Request $request, int $id)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');
        $suggestion = $this->find($host, $id)->load('client');
        $view = ['id' => $id, 'title' => $suggestion->title, 'from' => $suggestion->client?->nameForHost() ?? 'a friend'];
        $suggestion->delete();

        return response()->view('ham.host.partials._request_deleted', $view)->header('Content-Type', 'text/html');
    }

    private function find(Client $host, int $id): MediaRequest
    {
        return MediaRequest::query()->where('host_id', $host->host_id)->findOrFail($id);
    }

    /** The library title for an added suggestion: the one already there, or a new one. */
    private function addToList(MediaRequest $suggestion): LibraryItem
    {
        $type = $suggestion->type ?: 'film';
        // A new title waits in TBW ("Added, not in your collection yet")
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

    private function row(MediaRequest $suggestion, ?string $error = null)
    {
        return response()
            ->view('ham.host.partials._request_row', ['request' => $suggestion->load('client'), 'error' => $error])
            ->header('Content-Type', 'text/html');
    }
}
