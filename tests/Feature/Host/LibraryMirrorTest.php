<?php

namespace Tests\Feature\Host;

use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use App\Support\LibrarySource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Mirror mode ("My sheet is my whole library"): a sync also removes the
 * sheet's titles that the sheet no longer lists. Titles added in the app
 * through a suggestion, or pasted, stay. A sheet that suddenly looks much
 * smaller has its removals held back until the host says so.
 */
class LibraryMirrorTest extends TestCase
{
    use RefreshDatabase;

    private const SHEET = 'https://docs.google.com/spreadsheets/d/abc123/edit#gid=0';

    private Host $library;

    private Client $me;

    /** What the fake Google Sheet answers with; changed by sheet(). */
    private string $csv = "Title\n";

    protected function setUp(): void
    {
        parent::setUp();
        // One fake that reads $csv, since a later Http::fake() doesn't replace an earlier one.
        Http::fake(['docs.google.com/*' => fn () => Http::response($this->csv, 200, ['Content-Type' => 'text/csv'])]);
        $this->library = Host::factory()->create(['name' => "Christian's library", 'library_sheet_url' => self::SHEET]);
        $this->me = Client::factory()->host()->create(['host_id' => $this->library->id, 'display_name' => 'Christian']);
    }

    /** The sheet as Google would export it. */
    private function sheet(string ...$titles): void
    {
        $this->csv = "Title,Type,Year\n".implode("\n", $titles)."\n";
    }

    private function mirror(bool $on = true): void
    {
        $this->library->forceFill(['library_mirror' => $on])->save();
    }

    private function titles(): array
    {
        return LibraryItem::where('host_id', $this->library->id)->orderBy('title')->pluck('title')->all();
    }

    private function asHost(): static
    {
        return $this->withSession(['client_id' => $this->me->id]);
    }

    public function test_off_by_default_so_dropped_titles_stay(): void
    {
        $this->sheet('Heat,film,1995', 'Dune,film,2021');
        LibrarySource::sync($this->library->fresh());
        $this->sheet('Heat,film,1995');

        $this->assertSame(['new' => 0, 'removed' => 0, 'held' => 0], LibrarySource::syncReport($this->library->fresh()));
        $this->assertFalse($this->library->fresh()->library_mirror);
        $this->assertSame(['Dune', 'Heat'], $this->titles());
    }

    public function test_on_a_title_the_sheet_drops_leaves_the_library(): void
    {
        $this->mirror();
        $this->sheet('Heat,film,1995', 'Dune,film,2021', 'Alien,film,1979');
        LibrarySource::sync($this->library->fresh());
        $this->sheet('Heat,film,1995', 'Alien,film,1979', 'Arrival,film,2016');

        $report = LibrarySource::syncReport($this->library->fresh());

        $this->assertSame(['new' => 1, 'removed' => 1, 'held' => 0], $report);
        $this->assertSame(['Alien', 'Arrival', 'Heat'], $this->titles());
    }

    public function test_titles_added_through_a_suggestion_or_pasted_stay(): void
    {
        $this->mirror();
        $friend = Client::factory()->create(['host_id' => $this->library->id]);
        $suggestion = MediaRequest::factory()->create([
            'host_id' => $this->library->id, 'client_id' => $friend->id, 'title' => 'Paddington in Peru', 'year' => 2024, 'type' => 'film',
        ]);
        $this->asHost()->post("/host/requests/{$suggestion->id}/fulfill")->assertOk();
        LibrarySource::import($this->library, LibrarySource::parsePasted('Totoro (1988)'), LibraryItem::ORIGIN_PASTE);
        $this->sheet('Heat,film,1995');

        LibrarySource::sync($this->library->fresh());

        $this->assertSame(['Heat', 'Paddington in Peru', 'Totoro'], $this->titles());
    }

    public function test_a_title_renamed_in_the_app_is_matched_by_the_sheets_spelling(): void
    {
        $this->mirror();
        $this->sheet('Heaat,film,1995');
        LibrarySource::sync($this->library->fresh());
        LibraryItem::sole()->forceFill(['title' => 'Heat', 'edited_at' => now()])->save();

        LibrarySource::sync($this->library->fresh());
        $this->assertSame(['Heat'], $this->titles(), 'still listed as Heaat, so it stays');

        $this->sheet('Dune,film,2021');
        LibrarySource::sync($this->library->fresh());
        $this->assertSame(['Dune'], $this->titles());
    }

    public function test_a_suggestion_whose_title_leaves_still_says_added(): void
    {
        $this->mirror();
        $this->sheet('Heat,film,1995');
        LibrarySource::sync($this->library->fresh());
        $friend = Client::factory()->create(['host_id' => $this->library->id]);
        $suggestion = MediaRequest::factory()->fulfilled()->create([
            'host_id' => $this->library->id, 'client_id' => $friend->id, 'title' => 'Heat',
            'fulfilled_library_item_id' => LibraryItem::sole()->id,
        ]);
        $this->sheet('Dune,film,2021');

        LibrarySource::sync($this->library->fresh());

        $suggestion->refresh();
        $this->assertSame('fulfilled', $suggestion->status);
        $this->assertNull($suggestion->fulfilled_library_item_id);
    }

    public function test_an_empty_sheet_holds_back_removals(): void
    {
        $this->mirror();
        $this->sheet('Heat,film,1995', 'Dune,film,2021');
        LibrarySource::sync($this->library->fresh());
        $this->sheet();

        $this->assertSame(['new' => 0, 'removed' => 0, 'held' => 2], LibrarySource::syncReport($this->library->fresh()));
        $this->assertSame(['Dune', 'Heat'], $this->titles());
        $this->assertSame(2, $this->library->fresh()->library_mirror_held);
    }

    public function test_a_sheet_that_loses_most_of_a_big_library_holds_back_until_the_host_says_so(): void
    {
        $this->mirror();
        $all = array_map(fn ($n) => "Film {$n},film,2000", range(1, 30));
        $this->sheet(...$all);
        LibrarySource::sync($this->library->fresh());
        $this->sheet(...array_slice($all, 0, 10));

        $this->asHost()->post('/host/library-sync')->assertOk()
            ->assertSee("20 titles weren't removed", false)
            ->assertSee('action="/host/library-sync?force=1"', false);
        $this->assertSame(30, LibraryItem::count());

        $this->asHost()->post('/host/library-sync?force=1')->assertOk()
            ->assertSee('Synced: 0 new titles, 20 removed.', false)
            ->assertDontSee("weren't removed", false);
        $this->assertSame(10, LibraryItem::count());
        $this->assertNull($this->library->fresh()->library_mirror_held);
    }

    public function test_a_small_library_can_lose_most_of_its_titles(): void
    {
        $this->mirror();
        $this->sheet('Heat,film,1995', 'Dune,film,2021', 'Alien,film,1979');
        LibrarySource::sync($this->library->fresh());
        $this->sheet('Heat,film,1995');

        $this->assertSame(['new' => 0, 'removed' => 2, 'held' => 0], LibrarySource::syncReport($this->library->fresh()));
    }

    public function test_an_unreadable_sheet_removes_nothing(): void
    {
        $this->mirror();
        $this->sheet('Heat,film,1995');
        LibrarySource::sync($this->library->fresh());
        // A private sheet answers with Google's sign-in page: a fresh fake, so it replaces the CSV one.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['docs.google.com/*' => Http::response('<html>Sign in</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->assertNull(LibrarySource::syncReport($this->library->fresh()));
        $this->assertSame(['Heat'], $this->titles());
    }

    public function test_the_host_turns_it_on_and_it_syncs_straight_away(): void
    {
        $this->sheet('Heat,film,1995', 'Dune,film,2021');
        LibrarySource::sync($this->library->fresh());
        $this->sheet('Heat,film,1995');

        $this->asHost()->get('/host/library-source.ham')->assertOk()
            ->assertSee('My sheet is my whole library: off', false)
            ->assertSee('action="/host/library-mirror?on=1"', false)
            ->assertSee('confirm="', false);

        $this->asHost()->post('/host/library-mirror?on=1')->assertOk()
            ->assertSee('Mirror mode is on. Synced: 0 new titles, 1 removed.', false)
            ->assertSee('My sheet is my whole library: on', false);

        $this->assertTrue($this->library->fresh()->library_mirror);
        $this->assertSame(['Heat'], $this->titles());
    }

    public function test_the_host_turns_it_off(): void
    {
        $this->mirror();
        $this->library->forceFill(['library_mirror_held' => 5])->save();

        $this->asHost()->post('/host/library-mirror?on=0')->assertOk()
            ->assertSee('Mirror mode is off.', false);

        $this->assertFalse($this->library->fresh()->library_mirror);
        $this->assertNull($this->library->fresh()->library_mirror_held);
    }

    public function test_the_scheduled_sync_reports_removals(): void
    {
        $this->mirror();
        $this->sheet('Heat,film,1995', 'Dune,film,2021');
        LibrarySource::sync($this->library->fresh());
        $this->sheet('Heat,film,1995');

        $this->artisan('library:sync')
            ->expectsOutput("Host {$this->library->id}: 0 new titles, 1 removed.")
            ->assertSuccessful();
    }

    public function test_a_friend_cannot_turn_it_on(): void
    {
        $friend = Client::factory()->create(['host_id' => $this->library->id]);

        $this->withSession(['client_id' => $friend->id])->post('/host/library-mirror?on=1')->assertOk();

        $this->assertFalse($this->library->fresh()->library_mirror);
    }
}
