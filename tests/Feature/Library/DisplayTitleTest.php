<?php

namespace Tests\Feature\Library;

use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use App\Support\LibrarySource;
use App\Support\SortTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catalog-style sheets write "Matrix, The". The library shows "The Matrix"
 * and still sorts it under M; the sheet's own spelling is kept for matching
 * its row on the next sync.
 */
class DisplayTitleTest extends TestCase
{
    use RefreshDatabase;

    private Host $library;

    protected function setUp(): void
    {
        parent::setUp();
        $this->library = Host::factory()->create();
    }

    private function sync(string $csv): void
    {
        LibrarySource::import($this->library, LibrarySource::parseCsv($csv));
    }

    public function test_an_article_at_the_end_goes_back_in_front(): void
    {
        $this->assertSame('The Matrix', SortTitle::display('Matrix, The'));
        $this->assertSame('A Beautiful Mind', SortTitle::display('Beautiful Mind, A'));
        $this->assertSame('An Education', SortTitle::display('Education, An'));
        $this->assertSame('The Matrix', SortTitle::display('Matrix, the'));
        $this->assertSame('The Matrix', SortTitle::display('Matrix ,The'));
        $this->assertSame('The Good, the Bad and the Ugly', SortTitle::display('Good, the Bad and the Ugly, The'));
    }

    public function test_each_part_of_a_title_with_a_subtitle_is_fixed_on_its_own(): void
    {
        $this->assertSame('The Hobbit: An Unexpected Journey', SortTitle::display('Hobbit, The: An Unexpected Journey'));
        $this->assertSame('Batman: The Dark Knight', SortTitle::display('Batman: Dark Knight, The'));
        $this->assertSame('The Lord of the Rings: The Two Towers', SortTitle::display('Lord of the Rings, The: Two Towers, The'));
        $this->assertSame('Star Wars: Episode IV - A New Hope', SortTitle::display('Star Wars: Episode IV - New Hope, A'));
        $this->assertSame('Mission: Impossible', SortTitle::display('Mission: Impossible'));
        $this->assertSame('Star Wars: The Clone Wars', SortTitle::display('Star Wars: The Clone Wars'));
        $this->assertSame('The SpongeBob Movie: Sponge Out of Water', SortTitle::display('SpongeBob Movie, The: Sponge Out of Water'));
    }

    public function test_titles_without_one_are_left_alone(): void
    {
        foreach (['The Matrix', 'Paris, Texas', 'Me, Myself & Irene', 'Inception', 'Theater', 'Banana'] as $title) {
            $this->assertSame($title, SortTitle::display($title), $title);
        }
    }

    public function test_the_display_title_still_sorts_under_its_first_real_word(): void
    {
        $this->assertSame('Matrix, The', SortTitle::from(SortTitle::display('Matrix, The')));
        $this->assertSame('Hobbit: An Unexpected Journey, The', SortTitle::from(SortTitle::display('Hobbit, The: An Unexpected Journey')));
    }

    public function test_a_sheet_title_with_a_subtitle_reads_right_and_sorts_under_its_first_word(): void
    {
        $this->sync("Title,Year\n\"Hobbit, The: An Unexpected Journey\",2012\n\"Batman: Dark Knight, The\",2008\nInception,2010\n");
        $friend = Client::factory()->create(['host_id' => $this->library->id]);

        $this->withSession(['client_id' => $friend->id])->get('/library.ham')->assertOk()
            ->assertSeeInOrder(['Batman: The Dark Knight', 'The Hobbit: An Unexpected Journey', 'Inception'], false);
    }

    public function test_a_sheet_row_is_shown_with_the_article_in_front_and_keeps_the_sheets_spelling(): void
    {
        $this->sync("Title,Year\n\"Matrix, The\",1999\n");

        $item = LibraryItem::sole();
        $this->assertSame(['The Matrix', 'Matrix, The', 'Matrix, The'], [$item->title, $item->sort_title, $item->source_title]);
    }

    public function test_the_library_lists_display_titles_in_sort_order(): void
    {
        $this->sync("Title,Year\nZodiac,2007\n\"Matrix, The\",1999\nAlien,1979\n\"Beautiful Mind, A\",2001\n");
        $friend = Client::factory()->create(['host_id' => $this->library->id]);

        $this->withSession(['client_id' => $friend->id])->get('/library.ham')->assertOk()
            ->assertSeeInOrder(['Alien', 'A Beautiful Mind', 'The Matrix', 'Zodiac'], false)
            ->assertDontSee('Matrix, The', false);
    }

    public function test_titles_already_in_the_library_are_fixed_on_the_next_sync_and_not_duplicated(): void
    {
        // Imported before display titles: shown as the sheet wrote it.
        LibraryItem::factory()->create(['host_id' => $this->library->id, 'title' => 'Matrix, The', 'sort_title' => 'Matrix, The',
            'year' => 1999, 'type' => 'film', 'origin' => 'sheet', 'source_title' => 'Matrix, The', 'source_year' => 1999]);

        $this->sync("Title,Year\n\"Matrix, The\",1999\n");
        $this->sync("Title,Year\n\"Matrix, The\",1999\n");

        $item = LibraryItem::sole();
        $this->assertSame(['The Matrix', 'Matrix, The'], [$item->title, $item->source_title]);
    }

    public function test_a_title_the_host_renamed_keeps_its_name(): void
    {
        LibraryItem::factory()->create(['host_id' => $this->library->id, 'title' => 'Matrix (Director Cut)', 'sort_title' => 'Matrix (Director Cut)',
            'year' => 1999, 'type' => 'film', 'origin' => 'sheet', 'source_title' => 'Matrix, The', 'source_year' => 1999, 'edited_at' => now()]);

        $this->sync("Title,Year\n\"Matrix, The\",1999\n");

        $this->assertSame('Matrix (Director Cut)', LibraryItem::sole()->title);
    }

    public function test_a_rename_that_would_clash_with_another_title_is_skipped(): void
    {
        LibraryItem::factory()->create(['host_id' => $this->library->id, 'title' => 'The Matrix', 'year' => 1999, 'origin' => 'app']);
        LibraryItem::factory()->create(['host_id' => $this->library->id, 'title' => 'Matrix, The', 'year' => 1999, 'origin' => 'sheet',
            'source_title' => 'Matrix, The', 'source_year' => 1999]);

        $this->sync("Title,Year\n\"Matrix, The\",1999\n");

        $this->assertSame(['Matrix, The', 'The Matrix'], LibraryItem::orderBy('title')->pluck('title')->all());
    }

    public function test_mirror_mode_still_matches_rows_by_the_sheets_spelling(): void
    {
        $this->library->forceFill(['library_mirror' => true])->save();
        $this->sync("Title,Year\n\"Matrix, The\",1999\nAlien,1979\n");

        [$removed] = LibrarySource::removeMissing($this->library, LibrarySource::parseCsv("Title,Year\n\"Matrix, The\",1999\n"));

        $this->assertSame(1, $removed);
        $this->assertSame(['The Matrix'], LibraryItem::pluck('title')->all());
    }

    public function test_a_suggestion_for_the_matrix_finds_the_sheets_title(): void
    {
        $this->sync("Title,Year,Type\n\"Matrix, The\",1999,film\n");
        $host = Client::factory()->host()->create(['host_id' => $this->library->id]);
        $friend = Client::factory()->create(['host_id' => $this->library->id]);
        $suggestion = MediaRequest::factory()->create(['host_id' => $this->library->id, 'client_id' => $friend->id,
            'title' => 'The Matrix', 'year' => 1999, 'type' => 'film']);

        $this->withSession(['client_id' => $host->id])->post("/host/requests/{$suggestion->id}/fulfill")->assertOk();

        $this->assertSame(1, LibraryItem::count());
    }

    public function test_the_edit_screen_doesnt_call_an_article_move_an_edit(): void
    {
        $this->sync("Title,Year\n\"Matrix, The\",1999\n");
        $host = Client::factory()->host()->create(['host_id' => $this->library->id]);
        $item = LibraryItem::sole();

        $this->withSession(['client_id' => $host->id])->get("/host/library/{$item->id}/edit.ham?in=page")->assertOk()
            ->assertDontSee('Edited in MoviePlz', false);
    }

    public function test_pasted_titles_are_shown_the_same_way(): void
    {
        LibrarySource::import($this->library, LibrarySource::parsePasted("Matrix, The (1999)\nGodfather, The"), LibraryItem::ORIGIN_PASTE);

        $this->assertSame(['The Godfather', 'The Matrix'], LibraryItem::orderBy('sort_title')->pluck('title')->all());
    }

    public function test_a_quoted_title_with_a_comma_stays_whole_and_its_row_lines_up(): void
    {
        $rows = LibrarySource::parseCsv("Title,Year,Type,TMDB\n\"Matrix, The\",1999,Film,603\n\"Good, the Bad and the Ugly, The\",1966,Film,429\n");

        $this->assertSame(['The Matrix', 1999, 'film', '603'], [$rows[0]['title'], $rows[0]['year'], $rows[0]['type'], $rows[0]['tmdb_id']]);
        $this->assertSame(['The Good, the Bad and the Ugly', 1966, '429'], [$rows[1]['title'], $rows[1]['year'], $rows[1]['tmdb_id']]);
    }

    public function test_a_title_the_old_parser_cut_at_its_comma_is_repaired_not_added_again(): void
    {
        // What the old parser stored for "Matrix, The",1999,Film: "Matrix", no year.
        $old = LibraryItem::factory()->create(['host_id' => $this->library->id, 'title' => 'Matrix', 'sort_title' => 'Matrix',
            'year' => null, 'type' => 'film', 'origin' => 'sheet', 'source_title' => 'Matrix', 'source_year' => null,
            'tmdb_id' => '1', 'tmdb_checked_at' => now()]);

        $this->sync("Title,Year,Type\n\"Matrix, The\",1999,Film\n");

        $item = LibraryItem::sole();
        $this->assertSame($old->id, $item->id);
        $this->assertSame(['The Matrix', 1999, 'Matrix, The', 1999, 'Matrix, The'],
            [$item->title, $item->year, $item->source_title, $item->source_year, $item->sort_title]);
        $this->assertNull($item->tmdb_checked_at, 'its poster is looked up again');
    }

    public function test_a_cut_title_the_host_renamed_keeps_its_name_but_gets_the_sheets_spelling(): void
    {
        LibraryItem::factory()->create(['host_id' => $this->library->id, 'title' => 'The Matrix', 'year' => 1999, 'type' => 'film',
            'origin' => 'sheet', 'source_title' => 'Matrix', 'source_year' => null, 'edited_at' => now()]);

        $this->sync("Title,Year,Type\n\"Matrix, The\",1999,Film\n");

        $item = LibraryItem::sole();
        $this->assertSame(['The Matrix', 'Matrix, The', 1999], [$item->title, $item->source_title, $item->source_year]);
    }

    public function test_mirror_mode_keeps_a_repaired_title(): void
    {
        $this->library->forceFill(['library_mirror' => true, 'library_sheet_url' => 'https://docs.google.com/spreadsheets/d/x/edit'])->save();
        LibraryItem::factory()->create(['host_id' => $this->library->id, 'title' => 'Matrix', 'year' => null, 'type' => 'film',
            'origin' => 'sheet', 'source_title' => 'Matrix', 'source_year' => null]);
        \Illuminate\Support\Facades\Http::fake(['docs.google.com/*' => \Illuminate\Support\Facades\Http::response("Title,Year\n\"Matrix, The\",1999\n", 200, ['Content-Type' => 'text/csv'])]);

        $report = LibrarySource::syncReport($this->library->fresh());

        $this->assertSame(['new' => 0, 'removed' => 0, 'held' => 0], $report);
        $this->assertSame(['The Matrix'], LibraryItem::pluck('title')->all());
    }
}
