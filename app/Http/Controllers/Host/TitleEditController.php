<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use App\Support\SortTitle;
use App\Support\TmdbSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The host edits a title: its name, year and film/series, then picks the
 * right poster and description from TMDB's likeliest matches (or none).
 *
 * Library titles are edited where they're shown: in a TBW row (?in=row)
 * or on the title page (?in=page). Every step answers with the same box,
 * swapped over itself: the form, the matches, then the row or page again.
 * Editing a title added in the app also corrects the suggestions that put
 * it there, so the friend sees the fixed name. A sheet title's edit is
 * the app's: the sheet isn't written, and sync goes by the sheet's own
 * spelling, so it neither duplicates nor undoes it.
 *
 * Pending suggestions (TBW's New and In progress) are edited in their row: the
 * same fields, no poster (they have none until "Added").
 */
class TitleEditController extends Controller
{
    /** GET /host/library/{id}/edit.ham */
    public function editForm(Request $request, int $id)
    {
        $item = $this->item($request, $id);

        return $this->form($item, $this->in($request), $item->title, $item->year, $item->type);
    }

    /** POST /host/library/{id}/edit — save; when the title changed, the matches to pick from. */
    public function update(Request $request, int $id)
    {
        $item = $this->item($request, $id);
        $in = $this->in($request);
        [$data, $error] = $this->validated($request, typeRequired: true);

        if (! $error) {
            $clash = LibraryItem::query()->where('host_id', $item->host_id)->where('id', '!=', $item->id)
                ->where('title', $data['title'])->where('year', $data['year'])->exists();
            if ($clash) {
                $error = 'Your library already has '.$data['title'].($data['year'] ? " ({$data['year']})" : '').'.';
            }
        }
        if ($error) {
            return $this->form($item, $in, $data['title'], $data['year'], $data['type'], $error);
        }

        $changed = $data['title'] !== $item->title || $data['year'] !== $item->year || $data['type'] !== $item->type;
        $item->forceFill([
            'title' => $data['title'],
            'sort_title' => SortTitle::from($data['title']),
            'year' => $data['year'],
            'type' => $data['type'],
            'edited_at' => now(),
        ] + ($request->boolean('keep') ? ['in_collection' => true] : [])
          // A new name needs a new poster: until one is picked, the
          // nightly lookup may find it.
          + ($changed ? ['tmdb_id' => null, 'poster_url' => null, 'overview' => null, 'tmdb_checked_at' => null] : []))->save();

        // The friends' suggestions that put it on the list say the same.
        $item->suggestions()->update(['title' => $data['title'], 'year' => $data['year'], 'type' => $data['type']]);

        return $changed ? $this->matches($item, $in) : $this->box($item, $in);
    }

    /** GET /host/library/{id}/matches.ham — pick the poster again, without renaming. */
    public function matchList(Request $request, int $id)
    {
        return $this->matches($this->item($request, $id), $this->in($request));
    }

    /** POST /host/library/{id}/match?pick=N|none */
    public function match(Request $request, int $id)
    {
        $item = $this->item($request, $id);
        $in = $this->in($request);
        $pick = (string) $request->query('pick', '');

        if ($pick === 'none') {
            $item->forceFill(['tmdb_id' => null, 'poster_url' => null, 'overview' => null, 'tmdb_checked_at' => now()])->save();

            return $this->box($item, $in);
        }

        $candidates = Cache::get($this->cacheKey($item));
        $chosen = is_array($candidates) && ctype_digit($pick) ? ($candidates[(int) $pick] ?? null) : null;
        if (! $chosen) {
            return $this->matches($item, $in, 'Those matches expired. Here they are again.');
        }

        $item->forceFill([
            'tmdb_id' => $chosen['tmdb_id'],
            'poster_url' => $chosen['poster_url'],
            'overview' => $chosen['overview'],
            'tmdb_checked_at' => now(),
        ])->save();
        Cache::forget($this->cacheKey($item));

        return $this->box($item, $in);
    }

    /** GET /host/library/{id}/view.ham — the row or page again (Cancel, Done). */
    public function view(Request $request, int $id)
    {
        return $this->box($this->item($request, $id), $this->in($request));
    }

    /** GET /host/requests/{id}/edit.ham — a pending suggestion's fields. */
    public function suggestionForm(Request $request, int $id)
    {
        $suggestion = $this->pendingSuggestion($request, $id);

        return $this->suggestionFormView($suggestion, $suggestion->title, $suggestion->year, $suggestion->type);
    }

    /** POST /host/requests/{id}/edit */
    public function updateSuggestion(Request $request, int $id)
    {
        $suggestion = $this->pendingSuggestion($request, $id);
        [$data, $error] = $this->validated($request, typeRequired: false);
        if ($error) {
            return $this->suggestionFormView($suggestion, $data['title'], $data['year'], $data['type'], $error);
        }

        $suggestion->forceFill($data)->save();

        return $this->suggestionRow($suggestion);
    }

    /** GET /host/requests/{id}/row.ham — the suggestion's row again (Cancel). */
    public function suggestionRowView(Request $request, int $id)
    {
        return $this->suggestionRow($this->suggestion($request, $id));
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function host(Request $request): Client
    {
        return $request->attributes->get('current_host');
    }

    private function item(Request $request, int $id): LibraryItem
    {
        return LibraryItem::query()->where('host_id', $this->host($request)->host_id)->findOrFail($id);
    }

    private function suggestion(Request $request, int $id): MediaRequest
    {
        return MediaRequest::query()->where('host_id', $this->host($request)->host_id)->with('client')->findOrFail($id);
    }

    private function pendingSuggestion(Request $request, int $id): MediaRequest
    {
        $suggestion = $this->suggestion($request, $id);
        // A seen one is edited through its library title, in TBW or on its page.
        abort_unless($suggestion->status === MediaRequest::STATUS_PENDING, 404);

        return $suggestion;
    }

    private function in(Request $request): string
    {
        return $request->query('in') === 'page' ? 'page' : 'row';
    }

    /** @return array{0: array{title: string, year: ?int, type: ?string}, 1: ?string} */
    private function validated(Request $request, bool $typeRequired): array
    {
        $data = [
            'title' => trim((string) $request->input('title', '')),
            'year' => trim((string) $request->input('year', '')),
            'type' => (string) $request->input('type', ''),
        ];
        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'year' => ['nullable', 'integer', 'min:1870', 'max:'.(now()->year + 5)],
            'type' => [$typeRequired ? 'required' : 'nullable', Rule::in(['film', 'series'])],
        ], [
            'title.required' => 'Enter a title.',
            'title.max' => 'Titles can be at most 200 characters.',
            'year.integer' => 'Years are four digits, like 1995.',
            'year.min' => 'Years are four digits, like 1995.',
            'year.max' => 'Years are four digits, like 1995.',
            'type.required' => 'Pick Film or Series.',
            'type.in' => 'Pick Film or Series.',
        ]);

        $clean = [
            'title' => $data['title'],
            'year' => $data['year'] !== '' && ctype_digit($data['year']) ? (int) $data['year'] : null,
            'type' => in_array($data['type'], ['film', 'series'], true) ? $data['type'] : null,
        ];

        return [$clean, $validator->fails() ? $validator->errors()->first() : null];
    }

    private function cacheKey(LibraryItem $item): string
    {
        return "tmdb-pick:{$item->id}";
    }

    private function boxId(LibraryItem $item, string $in): string
    {
        return $in === 'page' ? "title-{$item->id}" : "tbw-item-{$item->id}";
    }

    private function form(LibraryItem $item, string $in, string $title, ?int $year, ?string $type, ?string $error = null)
    {
        return $this->ham('ham.host.partials._title_edit', [
            'item' => $item, 'in' => $in, 'box' => $this->boxId($item, $in),
            'title' => $title, 'year' => $year, 'type' => $type, 'error' => $error,
        ]);
    }

    private function matches(LibraryItem $item, string $in, ?string $notice = null)
    {
        $candidates = TmdbSearch::candidates($item->title, $item->year, $item->type);
        if ($candidates) {
            Cache::put($this->cacheKey($item), $candidates, now()->addMinutes(30));
        }

        return $this->ham('ham.host.partials._title_matches', [
            'item' => $item, 'in' => $in, 'box' => $this->boxId($item, $in),
            'candidates' => $candidates, 'notice' => $notice,
        ]);
    }

    private function box(LibraryItem $item, string $in)
    {
        return $in === 'page'
            ? $this->ham('ham.client.partials._title_main', ['item' => $item])
            : $this->ham('ham.host.partials._tbw_item', ['item' => $item->load('suggestions.client')]);
    }

    private function suggestionFormView(MediaRequest $suggestion, string $title, ?int $year, ?string $type, ?string $error = null)
    {
        return $this->ham('ham.host.partials._request_edit', [
            'request' => $suggestion, 'title' => $title, 'year' => $year, 'type' => $type, 'error' => $error,
        ]);
    }

    private function suggestionRow(MediaRequest $suggestion)
    {
        return $this->ham('ham.host.partials._request_row', ['request' => $suggestion->load('client')]);
    }

    private function ham(string $view, array $data)
    {
        return response()->view($view, $data)->header('Content-Type', 'text/html');
    }
}
