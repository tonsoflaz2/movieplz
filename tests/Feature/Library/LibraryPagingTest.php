<?php

namespace Tests\Feature\Library;

use App\Http\Controllers\Client\LibraryController;
use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The library list loads a page at a time and fetches the next one while
 * PREFETCH_AHEAD rows are still below the screen, so scrolling doesn't stop
 * at each page's end.
 */
class LibraryPagingTest extends TestCase
{
    use RefreshDatabase;

    private Client $friend;

    private Host $library;

    protected function setUp(): void
    {
        parent::setUp();
        $this->library = Host::factory()->create();
        $this->friend = Client::factory()->create(['host_id' => $this->library->id]);
    }

    private function titles(int $count): void
    {
        foreach (range(1, $count) as $n) {
            $title = sprintf('Film %03d', $n);
            LibraryItem::factory()->create(['host_id' => $this->library->id, 'title' => $title, 'sort_title' => strtolower($title), 'year' => 2000, 'type' => 'film']);
        }
    }

    /** The titles in a response, in order. */
    private function listed(string $ham): array
    {
        preg_match_all('/<p style="font-base font-bold">([^<]+)<\/p>/', $ham, $m);

        return $m[1];
    }

    public function test_a_page_is_forty_titles_with_the_prefetch_fifteen_rows_before_its_end(): void
    {
        $this->titles(100);

        $ham = $this->withSession(['client_id' => $this->friend->id])->get('/library.ham')->assertOk()->getContent();

        $this->assertCount(LibraryController::PAGE, $this->listed($ham));
        $this->assertSame(40, LibraryController::PAGE);
        // The trigger sits just before row 26 (40 - 15) and swaps the placeholder at the end.
        $before = strstr($ham, 'trigger="revealed"', true);
        $this->assertCount(25, $this->listed($before));
        $this->assertStringContainsString('<block action="/library/search.ham?offset=40" target="#load-more-40" swap="outer" trigger="revealed" style="h-1"></block>', $ham);
        $this->assertStringContainsString('<block id="load-more-40">', $ham);
        $this->assertSame(1, substr_count($ham, 'trigger="revealed"'));
        $this->assertGreaterThan(strpos($ham, 'Film 040'), strpos($ham, 'id="load-more-40"'), 'the placeholder comes after the last row');
    }

    public function test_pages_follow_on_with_no_title_twice_or_missing(): void
    {
        $this->titles(95);
        $seen = [];
        $offset = 0;

        while (true) {
            $ham = $this->withSession(['client_id' => $this->friend->id])->get("/library/search.ham?offset={$offset}")->getContent();
            $seen = array_merge($seen, $this->listed($ham));
            if (! str_contains($ham, 'id="load-more-')) {
                break;
            }
            $offset += LibraryController::PAGE;
        }

        $this->assertSame(array_map(fn ($n) => sprintf('Film %03d', $n), range(1, 95)), $seen);
    }

    public function test_titles_that_share_a_sort_title_dont_repeat_across_pages(): void
    {
        // 41 remakes of the same name: the page break falls between them.
        foreach (range(1950, 1990) as $year) {
            LibraryItem::factory()->create(['host_id' => $this->library->id, 'title' => 'Dune', 'sort_title' => 'dune', 'year' => $year, 'type' => 'film']);
        }

        $first = $this->withSession(['client_id' => $this->friend->id])->get('/library.ham')->getContent();
        $second = $this->withSession(['client_id' => $this->friend->id])->get('/library/search.ham?offset=40')->getContent();
        preg_match_all('/navigate:\/library\/(\d+)\.ham/', $first.$second, $ids);

        $this->assertCount(41, $ids[1]);
        $this->assertCount(41, array_unique($ids[1]));
    }

    public function test_the_last_page_has_no_trigger_or_placeholder(): void
    {
        $this->titles(30);

        $this->withSession(['client_id' => $this->friend->id])->get('/library.ham')->assertOk()
            ->assertDontSee('trigger="revealed"', false)
            ->assertDontSee('load-more-', false);
    }

    public function test_a_search_pages_the_same_way_and_keeps_its_query(): void
    {
        $this->titles(60);

        $this->withSession(['client_id' => $this->friend->id])->get('/library/search.ham?q=film')->assertOk()
            ->assertSee('action="/library/search.ham?offset=40&amp;q=film"', false);
    }
}
