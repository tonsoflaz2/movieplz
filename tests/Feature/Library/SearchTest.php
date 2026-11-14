<?php

namespace Tests\Feature\Library;

use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_matching_titles_only(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->create(['host_id' => $host->id]);

        LibraryItem::factory()->create([
            'host_id' => $host->id,
            'title' => 'The Matrix',
            'sort_title' => 'Matrix, The',
            'type' => 'film',
            'year' => 1999,
        ]);
        LibraryItem::factory()->create([
            'host_id' => $host->id,
            'title' => 'Inception',
            'sort_title' => 'Inception',
            'type' => 'film',
            'year' => 2010,
        ]);

        $response = $this->withSession(['client_id' => $client->id])
            ->get('/library/search.ham?q=matrix');

        $response->assertOk();
        $response->assertSee('The Matrix', false);
        $response->assertDontSee('Inception', false);
    }

    public function test_rows_get_small_posters_and_the_title_page_a_big_one(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->create(['host_id' => $host->id]);
        $item = LibraryItem::factory()->create(['host_id' => $host->id, 'title' => 'Dune',
            'poster_url' => 'https://image.tmdb.org/t/p/w500/dune.jpg']);

        $this->withSession(['client_id' => $client->id])->get('/library/search.ham?q=dune')
            ->assertSee('https://image.tmdb.org/t/p/w185/dune.jpg', false)
            ->assertDontSee('/w500/', false);

        $this->withSession(['client_id' => $client->id])->get("/library/{$item->id}.ham")
            ->assertSee('https://image.tmdb.org/t/p/w780/dune.jpg', false);
    }

    public function test_empty_query_returns_all(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->create(['host_id' => $host->id]);

        LibraryItem::factory()->count(3)->create(['host_id' => $host->id]);

        $response = $this->withSession(['client_id' => $client->id])
            ->get('/library/search.ham?q=');

        $response->assertOk();
        $response->assertSee('rounded-lg mb-8', false); // library cards rendered
    }

    public function test_no_match_shows_suggest_cta(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->create(['host_id' => $host->id]);

        $response = $this->withSession(['client_id' => $client->id])
            ->get('/library/search.ham?q=nonexistent');

        $response->assertOk();
        $response->assertSee('No matches for', false);
        $response->assertSee('Suggest "nonexistent"', false);
    }

    public function test_search_is_scoped_to_current_host(): void
    {
        $hostA = Host::factory()->create();
        $hostB = Host::factory()->create();
        $clientA = Client::factory()->create(['host_id' => $hostA->id]);

        LibraryItem::factory()->create([
            'host_id' => $hostB->id,
            'title' => 'Matrix Reloaded',
            'sort_title' => 'Matrix Reloaded',
        ]);

        $response = $this->withSession(['client_id' => $clientA->id])
            ->get('/library/search.ham?q=matrix');

        $response->assertOk();
        $response->assertDontSee('Matrix Reloaded', false);
    }
}
