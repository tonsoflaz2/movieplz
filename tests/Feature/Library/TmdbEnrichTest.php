<?php

namespace Tests\Feature\Library;

use App\Models\Host;
use App\Models\LibraryItem;
use App\Support\LibrarySource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TmdbEnrichTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tmdb.read_token' => 'test-token']);
    }

    private function item(Host $host, array $attrs = []): LibraryItem
    {
        return LibraryItem::factory()->create($attrs + ['host_id' => $host->id, 'title' => 'Heat', 'year' => 1995, 'type' => 'film',
            'poster_url' => null, 'overview' => null, 'tmdb_id' => null, 'tmdb_checked_at' => null]);
    }

    public function test_a_match_gets_poster_and_overview(): void
    {
        Http::fake(['api.themoviedb.org/*' => Http::response(['results' => [
            ['id' => 949, 'poster_path' => '/heat.jpg', 'overview' => 'A heist.'],
        ]])]);
        $item = $this->item(Host::factory()->create());

        $this->artisan('tmdb:enrich')->assertSuccessful();

        $item->refresh();
        $this->assertSame('949', $item->tmdb_id);
        $this->assertStringEndsWith('/heat.jpg', $item->poster_url);
        $this->assertSame('A heist.', $item->overview);
        $this->assertNotNull($item->tmdb_checked_at);
    }

    public function test_no_match_is_marked_checked_and_not_retried(): void
    {
        Http::fake(['api.themoviedb.org/*' => Http::response(['results' => []])]);
        $item = $this->item(Host::factory()->create(), ['title' => 'Home Movies 2003']);

        $this->artisan('tmdb:enrich')->assertSuccessful();
        $this->assertNotNull($item->refresh()->tmdb_checked_at);

        Http::fake(fn () => throw new \RuntimeException('should not be called'));
        $this->artisan('tmdb:enrich')->expectsOutputToContain('Nothing to enrich')->assertSuccessful();
    }

    public function test_a_tmdb_error_is_retried_next_run(): void
    {
        Http::fake(['api.themoviedb.org/*' => Http::response([], 503)]);
        $item = $this->item(Host::factory()->create());

        $this->artisan('tmdb:enrich')->assertSuccessful();

        $this->assertNull($item->refresh()->tmdb_checked_at);
    }

    public function test_a_title_another_library_has_is_copied_without_asking_tmdb(): void
    {
        Http::fake();
        $this->item(Host::factory()->create(), ['tmdb_id' => '949', 'poster_url' => 'https://img/heat.jpg',
            'overview' => 'A heist.', 'tmdb_checked_at' => now()]);
        $mine = $this->item(Host::factory()->create());

        $this->artisan('tmdb:enrich')->assertSuccessful();

        $this->assertSame('https://img/heat.jpg', $mine->refresh()->poster_url);
        Http::assertNothingSent();
    }

    public function test_an_import_gets_known_posters_at_once(): void
    {
        $this->item(Host::factory()->create(), ['tmdb_id' => '949', 'poster_url' => 'https://img/heat.jpg',
            'overview' => 'A heist.', 'tmdb_checked_at' => now()]);
        $host = Host::factory()->create();

        LibrarySource::import($host, LibrarySource::parsePasted("Heat (1995)\nSomething New"));

        $this->assertSame('https://img/heat.jpg', $host->libraryItems()->where('title', 'Heat')->value('poster_url'));
        $this->assertNull($host->libraryItems()->where('title', 'Something New')->value('poster_url'));
    }
}
