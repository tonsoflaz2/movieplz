<?php

namespace Tests\Feature\Host;

use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use App\Support\LibrarySource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LibrarySourceTest extends TestCase
{
    use RefreshDatabase;

    private const SHEET = 'https://docs.google.com/spreadsheets/d/abc123/edit#gid=7';

    private function hostClient(): Client
    {
        $host = Host::factory()->create();

        return Client::factory()->host()->create(['host_id' => $host->id]);
    }

    public function test_host_setup_creates_a_named_empty_library(): void
    {
        Http::fake();

        $response = $this->post('/onboarding/host-setup', ['display_name' => 'Christian']);

        $response->assertOk();
        $response->assertSee("Christian's library is ready");
        $host = Host::sole();
        $this->assertSame("Christian's library", $host->name);
        $this->assertSame('Christian', $host->hostClient()->display_name);
        $this->assertSame(0, $host->libraryItems()->count());
        $this->assertNull($host->library_sheet_url);
        Http::assertNothingSent();
    }

    public function test_host_setup_needs_a_name(): void
    {
        $this->post('/onboarding/host-setup', ['display_name' => ' '])
            ->assertOk()
            ->assertSee('Enter your name', false);

        $this->assertSame(0, Host::count());
    }

    public function test_share_link_becomes_csv_export_url(): void
    {
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/abc123/export?format=csv&gid=7',
            LibrarySource::csvUrl(self::SHEET),
        );
        $this->assertNull(LibrarySource::csvUrl('https://example.com/list.csv'));
    }

    public function test_csv_columns_are_found_by_header(): void
    {
        $rows = LibrarySource::parseCsv("Title,Plex/BR,Type,Year\n1917,Both,Film,2019\nThe Bear,Plex,Series,\n");

        $this->assertSame(['1917', 'The Bear'], array_column($rows, 'title'));
        $this->assertSame(['film', 'series'], array_column($rows, 'type'));
        $this->assertSame([2019, null], array_column($rows, 'year'));
    }

    public function test_pasted_titles_understand_years_and_series(): void
    {
        $rows = LibrarySource::parsePasted("Dune (2021)\nThe Bear, series\n\nHeat 1995\nAlien\nTed Lasso, series (2020)");

        $this->assertSame(['Dune', 'The Bear', 'Heat', 'Alien', 'Ted Lasso'], array_column($rows, 'title'));
        $this->assertSame([2021, null, 1995, null, 2020], array_column($rows, 'year'));
        $this->assertSame(['film', 'series', 'film', 'film', 'series'], array_column($rows, 'type'));
    }

    public function test_linking_a_sheet_imports_it(): void
    {
        Http::fake(['docs.google.com/*' => Http::response("Title,Type,Year\nHeat,film,1995\n", 200, ['Content-Type' => 'text/csv'])]);
        $me = $this->hostClient();

        $this->withSession(['client_id' => $me->id])
            ->post('/host/library-source', ['sheet_url' => self::SHEET])
            ->assertOk()
            ->assertSee('Added 1 new title.', false);

        $this->assertSame(self::SHEET, $me->host->fresh()->library_sheet_url);
        $this->assertDatabaseHas('library_items', ['host_id' => $me->host_id, 'title' => 'Heat', 'year' => 1995]);
    }

    public function test_a_private_sheet_is_explained_and_not_saved(): void
    {
        Http::fake(['docs.google.com/*' => Http::response('<html>Sign in</html>', 200, ['Content-Type' => 'text/html'])]);
        $me = $this->hostClient();

        $this->withSession(['client_id' => $me->id])
            ->post('/host/library-source', ['sheet_url' => self::SHEET])
            ->assertOk()
            ->assertSee('Anyone with the link', false);

        $this->assertNull($me->host->fresh()->library_sheet_url);
    }

    public function test_pasting_titles_adds_them(): void
    {
        $me = $this->hostClient();

        $this->withSession(['client_id' => $me->id])
            ->post('/host/library-paste', ['titles' => "Dune (2021)\nHeat 1995"])
            ->assertOk()
            ->assertSee('Added 2 new titles.', false);

        $this->assertSame(2, LibraryItem::where('host_id', $me->host_id)->count());
    }

    public function test_sync_only_touches_hosts_with_their_own_sheet(): void
    {
        Http::fake(['docs.google.com/*' => Http::response("Title\nHeat\n", 200, ['Content-Type' => 'text/csv'])]);
        $linked = Host::factory()->create(['library_sheet_url' => self::SHEET]);
        $other = Host::factory()->create();

        $this->artisan('library:sync')->assertSuccessful();

        $this->assertSame(1, $linked->libraryItems()->count());
        $this->assertSame(0, $other->libraryItems()->count());
    }

    public function test_a_friend_cannot_reach_the_library_source(): void
    {
        $host = Host::factory()->create();
        $friend = Client::factory()->create(['host_id' => $host->id]);

        $this->withSession(['client_id' => $friend->id])
            ->post('/host/library-paste', ['titles' => 'Heat'])
            ->assertOk();

        $this->assertSame(0, LibraryItem::count());
    }
}
