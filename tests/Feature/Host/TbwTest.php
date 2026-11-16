<?php

namespace Tests\Feature\Host;

use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use App\Support\LibrarySource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TBW: everything friends ask a host to watch; titles added in the app
 * (by "Saw it!") wait there until the host keeps them; and the host edits
 * any title, picking its TMDB match.
 */
class TbwTest extends TestCase
{
    use RefreshDatabase;

    private const SHEET = 'https://docs.google.com/spreadsheets/d/abc123/edit#gid=7';

    private Host $library;

    private Client $me;

    private Client $sam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->library = Host::factory()->create(['name' => "Lazarus's library"]);
        $this->me = Client::factory()->host()->create(['host_id' => $this->library->id, 'display_name' => 'Lazarus']);
        $this->sam = Client::factory()->create(['host_id' => $this->library->id, 'display_name' => 'Sam']);
        config(['tmdb.read_token' => null]); // no TMDB unless a test fakes it
    }

    private function suggest(string $title, ?int $year = null, string $status = MediaRequest::STATUS_PENDING, array $extra = []): MediaRequest
    {
        return MediaRequest::factory()->create([
            'host_id' => $this->library->id, 'client_id' => $this->sam->id,
            'title' => $title, 'year' => $year, 'type' => 'film', 'status' => $status,
        ] + $extra);
    }

    private function asHost()
    {
        return $this->withSession(['client_id' => $this->me->id]);
    }

    private function sawIt(MediaRequest $suggestion): LibraryItem
    {
        $this->asHost()->post("/host/requests/{$suggestion->id}/fulfill")->assertOk();

        return LibraryItem::findOrFail($suggestion->fresh()->fulfilled_library_item_id);
    }

    private function fakeTmdb(array $movies): void
    {
        config(['tmdb.read_token' => 'test-token', 'tmdb.image_base' => 'https://image.tmdb.org/t/p/w500']);
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response(['results' => $movies]),
            'api.themoviedb.org/3/search/tv*' => Http::response(['results' => []]),
        ]);
    }

    // ── Where titles come from ──────────────────────────────────────────

    public function test_saw_it_on_a_new_title_adds_it_in_the_app_not_to_the_collection(): void
    {
        $item = $this->sawIt($this->suggest('Heat', 1995));

        $this->assertSame('app', $item->origin);
        $this->assertFalse($item->in_collection);
        $this->assertTrue($item->isAwaitingKeep());
    }

    public function test_saw_it_on_a_title_already_in_the_collection_leaves_it_there(): void
    {
        LibrarySource::import($this->library, LibrarySource::parsePasted('Heat (1995)'), LibraryItem::ORIGIN_PASTE);

        $item = $this->sawIt($this->suggest('Heat', 1995));

        $this->assertSame('paste', $item->origin);
        $this->assertTrue($item->in_collection);
        $this->assertSame(1, LibraryItem::count());
    }

    public function test_pasted_titles_are_the_collection(): void
    {
        $this->asHost()->post('/host/library-paste', ['titles' => "Heat (1995)\nAlien (1979)"])->assertOk();

        $this->assertSame(['paste'], LibraryItem::pluck('origin')->unique()->values()->all());
        $this->assertSame(0, LibraryItem::where('in_collection', false)->count());
    }

    // ── The TBW screen ──────────────────────────────────────────────────

    public function test_tbw_lists_new_up_next_and_seen_titles_not_in_the_collection(): void
    {
        $this->suggest('Arrival', 2016);
        $this->suggest('Dune', 2021, extra: ['up_next_at' => now()]);
        $this->sawIt($this->suggest('Heat', 1995));
        LibrarySource::import($this->library, LibrarySource::parsePasted('Alien (1979)'), LibraryItem::ORIGIN_PASTE);
        // Someone else's library stays out.
        $other = Host::factory()->create();
        LibraryItem::factory()->create(['host_id' => $other->id, 'title' => 'Not Mine', 'origin' => 'app', 'in_collection' => false]);

        $this->asHost()->get('/app.ham')->assertSee('action="navigate:/host/tbw.ham"', false)
            ->assertSee('TBW · 3')->assertSee('1 new · 1 in progress · 1 added, not in your collection');

        $this->asHost()->get('/host/tbw.ham')->assertOk()
            ->assertSeeInOrder(['New · 1', 'Arrival', 'In progress · 1', 'Dune', 'Added, not in your collection yet · 1', 'Heat', 'Suggested by Sam'])
            ->assertSee('Add to my collection')
            ->assertDontSee('Alien')
            ->assertDontSee('Not Mine');
    }

    public function test_tbw_is_the_hosts_only(): void
    {
        $this->sawIt($this->suggest('Heat', 1995));

        $this->withSession(['client_id' => $this->sam->id])->get('/host/tbw.ham')
            ->assertSee('Not authorized')->assertDontSee('Added, not in your collection');
        $item = LibraryItem::sole();
        $this->withSession(['client_id' => $this->sam->id])->post("/host/library/{$item->id}/keep")->assertSee('Not authorized');
        $this->assertFalse($item->fresh()->in_collection);
    }

    public function test_friends_still_see_titles_added_in_the_app_and_their_status(): void
    {
        $this->sawIt($this->suggest('Heat', 1995));

        $this->withSession(['client_id' => $this->sam->id])->get('/library.ham')->assertSee('Heat');
        $this->withSession(['client_id' => $this->sam->id])->get('/requests.ham')->assertSee('Heat')->assertSee('Added');
    }

    // ── Keep and remove ─────────────────────────────────────────────────

    public function test_add_to_my_collection_keeps_it_in_place(): void
    {
        $item = $this->sawIt($this->suggest('Heat', 1995));

        $this->asHost()->post("/host/library/{$item->id}/keep")->assertOk()
            ->assertSee('id="tbw-item-'.$item->id.'"', false)->assertSee('In your collection')->assertDontSee('Yes, remove');

        $this->assertTrue($item->fresh()->in_collection);
        $this->asHost()->get('/host/tbw.ham')->assertDontSee('Seen, not in your collection yet');
    }

    public function test_remove_takes_it_off_the_library_and_the_suggestion_stays_seen(): void
    {
        $suggestion = $this->suggest('Heat', 1995);
        $item = $this->sawIt($suggestion);

        $this->asHost()->post("/host/library/{$item->id}/remove")->assertOk()->assertSee('Removed Heat from your library');

        $this->assertModelMissing($item);
        $this->assertSame(MediaRequest::STATUS_FULFILLED, $suggestion->fresh()->status);
    }

    public function test_remove_wont_touch_a_collection_title(): void
    {
        LibrarySource::import($this->library, LibrarySource::parsePasted('Alien (1979)'), LibraryItem::ORIGIN_PASTE);
        $item = LibraryItem::sole();

        $this->asHost()->post("/host/library/{$item->id}/remove");

        $this->assertModelExists($item);
    }

    // ── The sheet ───────────────────────────────────────────────────────

    public function test_the_sheet_listing_a_title_added_in_the_app_puts_it_in_the_collection(): void
    {
        $item = $this->sawIt($this->suggest('Heat', 1995));
        $this->library->forceFill(['library_sheet_url' => self::SHEET])->save();
        Http::fake(['docs.google.com/*' => Http::response("Title,Type,Year\nHeat,film,1995\n", 200, ['Content-Type' => 'text/csv'])]);

        LibrarySource::sync($this->library->fresh());

        $item->refresh();
        $this->assertTrue($item->in_collection);
        $this->assertSame('Heat', $item->source_title);
        $this->assertSame(1, LibraryItem::count());
    }

    public function test_a_sheet_title_renamed_in_the_app_isnt_duplicated_or_undone_and_keeps_its_added_date(): void
    {
        $this->library->forceFill(['library_sheet_url' => self::SHEET])->save();
        Http::fake(['docs.google.com/*' => Http::response("Title,Type,Year\nHeaat,film,1995\n", 200, ['Content-Type' => 'text/csv'])]);
        LibrarySource::sync($this->library->fresh());
        $item = LibraryItem::sole();
        $added = $item->added_at->toDateTimeString();

        $this->travel(2)->hours();
        $this->asHost()->post("/host/library/{$item->id}/edit?in=page", ['title' => 'Heat', 'year' => '1995', 'type' => 'series'])->assertOk();
        LibrarySource::sync($this->library->fresh());
        LibrarySource::sync($this->library->fresh());

        $item->refresh();
        $this->assertSame(1, LibraryItem::count());
        $this->assertSame('Heat', $item->title);
        $this->assertSame('series', $item->type, 'the sheet does not undo the edit');
        $this->assertSame('Heaat', $item->source_title);
        $this->assertSame($added, $item->added_at->toDateTimeString());
        $this->asHost()->get("/host/library/{$item->id}/edit.ham?in=page")->assertSee('Your Google Sheet still says', false);
    }

    public function test_titles_kept_in_the_app_can_be_copied_for_the_sheet(): void
    {
        $this->library->forceFill(['library_sheet_url' => self::SHEET])->save();
        $item = $this->sawIt($this->suggest('Heat', 1995));
        $this->asHost()->post("/host/library/{$item->id}/keep");

        $this->asHost()->get('/host/tbw.ham')
            ->assertSee('Not in your Google Sheet yet · 1')
            ->assertSee('var-sheet="Heat, Film, 1995"', false)
            ->assertSee('clipboard:copy:$sheet', false);
    }

    // ── Editing ─────────────────────────────────────────────────────────

    public function test_editing_a_seen_title_fixes_the_friends_suggestion_and_offers_tmdb_matches(): void
    {
        $suggestion = $this->suggest('Heet', 1995);
        $item = $this->sawIt($suggestion);
        $this->fakeTmdb([
            ['id' => 949, 'title' => 'Heat', 'release_date' => '1995-12-15', 'poster_path' => '/heat.jpg', 'overview' => 'A heist.', 'vote_count' => 7000],
            ['id' => 1, 'title' => 'Heat', 'release_date' => '1986-01-01', 'poster_path' => null, 'overview' => 'Another.', 'vote_count' => 10],
        ]);

        $this->asHost()->get("/host/library/{$item->id}/edit.ham?in=row")->assertOk()
            ->assertSee('id="tbw-item-'.$item->id.'"', false)->assertSee('Save and add to my collection');

        $this->asHost()->post("/host/library/{$item->id}/edit?in=row", ['title' => 'Heat', 'year' => '1995', 'type' => 'film'])
            ->assertOk()
            ->assertSee('Which one is Heat (1995)?')
            ->assertSeeInOrder(['Heat', '1995', 'This one', 'Heat', '1986', 'This one']);

        $this->assertSame('Heat', $suggestion->fresh()->title);
        $this->assertSame('Heat', $item->fresh()->title);

        $this->asHost()->post("/host/library/{$item->id}/match?in=row&pick=0")->assertOk()
            ->assertSee('id="tbw-item-'.$item->id.'"', false)->assertSee('Add to my collection');
        $item->refresh();
        $this->assertSame('949', $item->tmdb_id);
        $this->assertSame('https://image.tmdb.org/t/p/w500/heat.jpg', $item->poster_url);
        $this->assertSame('A heist.', $item->overview);
        $this->assertNotNull($item->edited_at);

        $this->withSession(['client_id' => $this->sam->id])->get('/requests.ham')->assertSee('Heat')->assertDontSee('Heet');
    }

    public function test_save_and_add_keeps_it(): void
    {
        $item = $this->sawIt($this->suggest('Heat', 1995));

        $this->asHost()->post("/host/library/{$item->id}/edit?in=row&keep=1", ['title' => 'Heat', 'year' => '1995', 'type' => 'film'])
            ->assertOk()->assertSee('In your collection');

        $this->assertTrue($item->fresh()->in_collection);
    }

    public function test_no_poster_clears_it_and_a_missing_tmdb_is_said(): void
    {
        $item = $this->sawIt($this->suggest('Heat', 1995));
        $item->forceFill(['poster_url' => 'https://image.tmdb.org/t/p/w500/x.jpg', 'tmdb_id' => '5'])->save();

        $this->asHost()->get("/host/library/{$item->id}/matches.ham?in=page")->assertSee("Couldn't reach The Movie Database", false);

        $this->asHost()->post("/host/library/{$item->id}/match?in=page&pick=none")->assertOk()
            ->assertSee('id="title-'.$item->id.'"', false);
        $this->assertNull($item->fresh()->poster_url);
        $this->assertNotNull($item->fresh()->tmdb_checked_at, 'the nightly lookup leaves it alone');
    }

    public function test_an_edit_onto_a_title_the_library_has_is_refused(): void
    {
        LibrarySource::import($this->library, LibrarySource::parsePasted('Heat (1995)'), LibraryItem::ORIGIN_PASTE);
        $item = $this->sawIt($this->suggest('Heet', 1995));

        $this->asHost()->post("/host/library/{$item->id}/edit?in=row", ['title' => 'Heat', 'year' => '1995', 'type' => 'film'])
            ->assertOk()->assertSee('Your library already has Heat (1995).');

        $this->assertSame('Heet', $item->fresh()->title);
    }

    public function test_an_edit_needs_a_title_and_a_real_year(): void
    {
        $item = $this->sawIt($this->suggest('Heat', 1995));

        $this->asHost()->post("/host/library/{$item->id}/edit?in=row", ['title' => ' ', 'year' => '1995', 'type' => 'film'])
            ->assertSee('Enter a title.');
        $this->asHost()->post("/host/library/{$item->id}/edit?in=row", ['title' => 'Heat', 'year' => '95', 'type' => 'film'])
            ->assertSee('Years are four digits');
        $this->assertSame('Heat', $item->fresh()->title);
    }

    public function test_the_title_page_has_edit_for_the_host_only(): void
    {
        $item = $this->sawIt($this->suggest('Heat', 1995));

        $this->asHost()->get("/library/{$item->id}.ham")->assertSee('/host/library/'.$item->id.'/edit.ham?in=page', false)
            ->assertSee('id="title-'.$item->id.'"', false);
        $this->withSession(['client_id' => $this->sam->id])->get("/library/{$item->id}.ham")
            ->assertSee('Heat')->assertDontSee('/edit.ham', false)->assertDontSee('Add to my collection');
    }

    public function test_a_host_edits_a_pending_suggestion_in_its_row(): void
    {
        $suggestion = $this->suggest('Arival', 2061);

        $this->asHost()->get('/host/tbw.ham')->assertSee('/host/requests/'.$suggestion->id.'/edit.ham', false);
        $this->asHost()->get("/host/requests/{$suggestion->id}/edit.ham")->assertOk()->assertSee("Edit Sam's suggestion");

        $this->asHost()->post("/host/requests/{$suggestion->id}/edit", ['title' => 'Arrival', 'year' => '2016', 'type' => 'film'])
            ->assertOk()->assertSee('id="request-'.$suggestion->id.'"', false)->assertSee('Arrival')->assertSee('In progress', false);

        $suggestion->refresh();
        $this->assertSame(['Arrival', 2016, 'film'], [$suggestion->title, $suggestion->year, $suggestion->type]);
        $this->withSession(['client_id' => $this->sam->id])->get('/requests.ham')->assertSee('Arrival')->assertDontSee('Arival');
    }

    public function test_a_seen_suggestion_isnt_edited_as_a_suggestion(): void
    {
        $suggestion = $this->suggest('Heat', 1995);
        $this->sawIt($suggestion);

        $this->asHost()->post("/host/requests/{$suggestion->id}/edit", ['title' => 'Changed', 'year' => '', 'type' => ''])->assertOk();

        $this->assertSame('Heat', $suggestion->fresh()->title);
    }

    // ── The migration's backfill ────────────────────────────────────────

    public function test_the_backfill_finds_titles_added_by_saw_it(): void
    {
        $migration = require database_path('migrations/2026_11_13_000002_add_collection_to_library_items_table.php');
        $migration->down();

        $now = now();
        $sheetHost = Host::factory()->create(['library_sheet_url' => self::SHEET]);
        $fromSheet = DB::table('library_items')->insertGetId(['host_id' => $sheetHost->id, 'title' => 'Alien', 'sort_title' => 'Alien', 'type' => 'film', 'year' => 1979, 'added_at' => $now, 'created_at' => $now->copy()->subDays(9), 'updated_at' => $now]);
        $pasted = DB::table('library_items')->insertGetId(['host_id' => $this->library->id, 'title' => 'Dune', 'sort_title' => 'Dune', 'type' => 'film', 'year' => 2021, 'added_at' => $now, 'created_at' => $now->copy()->subDays(9), 'updated_at' => $now]);
        $byApp = DB::table('library_items')->insertGetId(['host_id' => $this->library->id, 'title' => 'Heat', 'sort_title' => 'Heat', 'type' => 'film', 'year' => 1995, 'added_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        // Seen, but the title was already there days before: not added by the app.
        $this->suggest('Dune', 2021, MediaRequest::STATUS_FULFILLED, ['fulfilled_at' => $now, 'fulfilled_library_item_id' => $pasted]);
        $this->suggest('Heat', 1995, MediaRequest::STATUS_FULFILLED, ['fulfilled_at' => $now->copy()->addSeconds(1), 'fulfilled_library_item_id' => $byApp]);

        $migration->up();

        $row = fn ($id) => DB::table('library_items')->find($id);
        $this->assertSame(['sheet', 1, 'Alien'], [$row($fromSheet)->origin, (int) $row($fromSheet)->in_collection, $row($fromSheet)->source_title]);
        $this->assertSame(['paste', 1, 'Dune'], [$row($pasted)->origin, (int) $row($pasted)->in_collection, $row($pasted)->source_title]);
        $this->assertSame(['app', 0, null], [$row($byApp)->origin, (int) $row($byApp)->in_collection, $row($byApp)->source_title]);
    }
}
