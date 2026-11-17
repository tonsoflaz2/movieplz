<?php

namespace Tests\Feature\Library;

use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use App\Support\LibrarySource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A sheet's TMDB, IMDb and TVDB columns: a title with IDs gets its poster
 * and details from that exact entry, tried in the order TMDB, IMDb, TVDB
 * (IMDb and TVDB through TMDB's /find), then a search by name as before.
 */
class SheetIdsTest extends TestCase
{
    use RefreshDatabase;

    private Host $library;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tmdb.read_token' => 'test-token']);
        $this->library = Host::factory()->create();
    }

    private function import(string $csv): void
    {
        LibrarySource::import($this->library, LibrarySource::parseCsv($csv));
    }

    private function movie(int $id, string $title, string $poster = '/p.jpg'): array
    {
        return ['id' => $id, 'title' => $title, 'poster_path' => $poster, 'overview' => "About {$title}."];
    }

    public function test_id_columns_are_found_by_header_in_any_order_and_spelling(): void
    {
        $rows = LibrarySource::parseCsv(
            "IMDb ID,Title,tvdb,Year,TMDB,Type\n"
            ."tt0113277,Heat,,1995,949,film\n"
            ."https://www.imdb.com/title/tt0903747/,Breaking Bad,81189,2008,https://www.themoviedb.org/tv/1396-breaking-bad,series\n"
            ."n/a,Glory,,1989,,film\n"
        );

        $this->assertSame(['tmdb_id' => '949', 'imdb_id' => 'tt0113277', 'tvdb_id' => null], array_intersect_key($rows[0], array_flip(['tmdb_id', 'imdb_id', 'tvdb_id'])));
        $this->assertSame(['1396', 'tt0903747', '81189'], [$rows[1]['tmdb_id'], $rows[1]['imdb_id'], $rows[1]['tvdb_id']]);
        $this->assertSame([null, null, null], [$rows[2]['tmdb_id'], $rows[2]['imdb_id'], $rows[2]['tvdb_id']]);
    }

    public function test_a_sheet_without_id_columns_works_as_before(): void
    {
        $rows = LibrarySource::parseCsv("Title,Year\nHeat,1995\n");

        $this->assertSame('Heat', $rows[0]['title']);
        $this->assertNull($rows[0]['tmdb_id']);
    }

    public function test_the_tmdb_id_comes_first(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/949*' => Http::response($this->movie(949, 'Heat', '/heat.jpg')),
            '*' => Http::response('unexpected', 500),
        ]);
        $this->import("Title,Year,TMDB,IMDb,TVDB\nHeat,1995,949,tt0113277,1234\n");

        $this->artisan('tmdb:enrich')->assertSuccessful();

        $item = LibraryItem::sole();
        $this->assertSame(['949', 'tmdb_id'], [$item->tmdb_id, $item->matched_by]);
        $this->assertStringEndsWith('/heat.jpg', $item->poster_url);
        $this->assertSame('About Heat.', $item->overview);
        Http::assertSentCount(1);
    }

    public function test_a_tmdb_id_that_finds_nothing_passes_to_the_imdb_id(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/999999*' => Http::response(['status_code' => 34], 404),
            'api.themoviedb.org/3/find/tt0113277*' => Http::response(['movie_results' => [$this->movie(949, 'Heat')], 'tv_results' => []]),
            '*' => Http::response('unexpected', 500),
        ]);
        $this->import("Title,Year,TMDB,IMDb\nHeat,1995,999999,tt0113277\n");

        $this->artisan('tmdb:enrich')->assertSuccessful();

        $item = LibraryItem::sole();
        $this->assertSame(['949', 'imdb_id'], [$item->tmdb_id, $item->matched_by]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'find/tt0113277') && $r['external_source'] === 'imdb_id');
    }

    public function test_the_tvdb_id_comes_last_and_prefers_the_titles_own_type(): void
    {
        Http::fake([
            'api.themoviedb.org/3/find/81189*' => Http::response([
                'movie_results' => [$this->movie(5, 'Breaking Bad: The Movie')],
                'tv_results' => [['id' => 1396, 'name' => 'Breaking Bad', 'poster_path' => '/bb.jpg', 'overview' => 'Chemistry.']],
            ]),
            '*' => Http::response('unexpected', 500),
        ]);
        $this->import("Title,Type,TVDB\nBreaking Bad,series,81189\n");

        $this->artisan('tmdb:enrich')->assertSuccessful();

        $item = LibraryItem::sole();
        $this->assertSame(['1396', 'tvdb_id'], [$item->tmdb_id, $item->matched_by]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'find/81189') && $r['external_source'] === 'tvdb_id');
    }

    public function test_a_series_tmdb_id_is_looked_up_as_tv(): void
    {
        Http::fake([
            'api.themoviedb.org/3/tv/1396*' => Http::response(['id' => 1396, 'name' => 'Breaking Bad', 'poster_path' => '/bb.jpg', 'overview' => 'x']),
            '*' => Http::response('unexpected', 500),
        ]);
        $this->import("Title,Type,TMDB\nBreaking Bad,series,1396\n");

        $this->artisan('tmdb:enrich')->assertSuccessful();

        $this->assertSame('1396', LibraryItem::sole()->tmdb_id);
    }

    public function test_when_no_id_works_it_searches_by_name(): void
    {
        Http::fake([
            'api.themoviedb.org/3/find/*' => Http::response(['movie_results' => [], 'tv_results' => []]),
            'api.themoviedb.org/3/search/*' => Http::response(['results' => [$this->movie(949, 'Heat')]]),
            '*' => Http::response('unexpected', 500),
        ]);
        $this->import("Title,Year,IMDb\nHeat,1995,tt9999999\n");

        $this->artisan('tmdb:enrich')->assertSuccessful();

        $item = LibraryItem::sole();
        $this->assertSame('949', $item->tmdb_id);
        $this->assertNull($item->matched_by);
    }

    public function test_a_tmdb_error_is_tried_again_next_run(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        $this->import("Title,TMDB\nHeat,949\n");

        $this->artisan('tmdb:enrich')->assertSuccessful();

        $this->assertNull(LibraryItem::sole()->tmdb_checked_at);
    }

    public function test_a_title_with_ids_isnt_copied_from_another_library_by_name(): void
    {
        $other = Host::factory()->create();
        LibraryItem::factory()->create(['host_id' => $other->id, 'title' => 'Heat', 'year' => 1995, 'type' => 'film',
            'tmdb_id' => '1', 'poster_url' => 'https://image.tmdb.org/t/p/w500/wrong.jpg', 'overview' => 'Wrong Heat.']);
        Http::fake([
            'api.themoviedb.org/3/movie/949*' => Http::response($this->movie(949, 'Heat', '/heat.jpg')),
            '*' => Http::response('unexpected', 500),
        ]);

        $this->import("Title,Year,TMDB\nHeat,1995,949\n");
        $mine = LibraryItem::where('host_id', $this->library->id)->sole();
        $this->assertNull($mine->tmdb_id, 'not copied at import');

        $this->artisan('tmdb:enrich', ['--host-id' => $this->library->id])->assertSuccessful();
        $this->assertSame('949', $mine->fresh()->tmdb_id);
    }

    public function test_changed_ids_get_the_title_looked_up_again_keeping_its_poster_meanwhile(): void
    {
        $this->import("Title,Year,IMDb\nHeat,1995,tt0113277\n");
        LibraryItem::sole()->forceFill(['tmdb_id' => '949', 'poster_url' => 'https://image.tmdb.org/t/p/w500/heat.jpg', 'tmdb_checked_at' => now(), 'matched_by' => 'imdb_id'])->save();

        $this->import("Title,Year,IMDb\nHeat,1995,tt0113277\n");
        $this->assertNotNull(LibraryItem::sole()->tmdb_checked_at, 'same IDs: left alone');

        $this->import("Title,Year,IMDb\nHeat,1995,tt9876543\n");
        $item = LibraryItem::sole();
        $this->assertSame('tt9876543', $item->source_imdb_id);
        $this->assertNull($item->tmdb_checked_at, 'looked up again');
        $this->assertSame('949', $item->tmdb_id, 'poster kept until then');
    }

    public function test_an_existing_title_that_already_has_the_sheets_tmdb_id_isnt_looked_up_again(): void
    {
        $this->import("Title,Year\nHeat,1995\n");
        LibraryItem::sole()->forceFill(['tmdb_id' => '949', 'tmdb_checked_at' => now()])->save();

        $this->import("Title,Year,TMDB\nHeat,1995,949\n");

        $item = LibraryItem::sole();
        $this->assertNotNull($item->tmdb_checked_at);
        $this->assertSame(['949', 'tmdb_id'], [$item->source_tmdb_id, $item->matched_by]);
    }

    public function test_a_match_the_host_picked_by_hand_beats_the_sheet(): void
    {
        $this->import("Title,Year,TMDB\nHeat,1995,949\n");
        $item = LibraryItem::sole();
        $item->forceFill(['tmdb_id' => '12345', 'tmdb_checked_at' => now(), 'matched_by' => 'host'])->save();

        $this->import("Title,Year,TMDB\nHeat,1995,555\n");
        Http::fake(['*' => Http::response('unexpected', 500)]);
        $this->artisan('tmdb:enrich', ['--force' => true])->assertSuccessful();

        $item->refresh();
        $this->assertSame(['12345', 'host', '555'], [$item->tmdb_id, $item->matched_by, $item->source_tmdb_id]);
        Http::assertNothingSent();
    }

    public function test_picking_a_match_in_the_app_marks_it_the_hosts(): void
    {
        $me = Client::factory()->host()->create(['host_id' => $this->library->id]);
        $this->import("Title,Year,TMDB\nHeat,1995,949\n");
        $item = LibraryItem::sole();

        $this->withSession(['client_id' => $me->id])->post("/host/library/{$item->id}/match?pick=none&in=page")->assertOk();

        $this->assertSame('host', $item->fresh()->matched_by);
    }
}
