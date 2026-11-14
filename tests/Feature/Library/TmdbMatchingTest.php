<?php

namespace Tests\Feature\Library;

use App\Models\Host;
use App\Models\LibraryItem;
use App\Support\LibrarySource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The titles DEMO pasted on Android (2026-11-08) and what TMDB answered,
 * trimmed to the fields that matter. Two of three got the wrong film.
 */
class TmdbMatchingTest extends TestCase
{
    use RefreshDatabase;

    private const MOVIE = [
        'Severance' => [
            ['id' => 5072, 'title' => 'Severance', 'release_date' => '2006-05-19', 'vote_count' => 706, 'poster_path' => '/film.jpg', 'overview' => 'Seven employees of an international weapons manufacturer…'],
            ['id' => 1205412, 'title' => 'Severance', 'release_date' => '2022-11-18', 'vote_count' => 3],
        ],
        'Spirited Away' => [
            ['id' => 1001196, 'title' => 'Spirited Away: Live on Stage', 'original_title' => '舞台『千と千尋の神隠し』', 'release_date' => '2023-04-23', 'vote_count' => 21, 'poster_path' => '/stage.jpg'],
            ['id' => 129, 'title' => 'Spirited Away', 'original_title' => '千と千尋の神隠し', 'release_date' => '2001-07-20', 'vote_count' => 19192, 'poster_path' => '/ghibli.jpg', 'overview' => 'A young girl, Chihiro…'],
        ],
        'Arrival' => [
            ['id' => 401867, 'title' => 'The Arrival', 'release_date' => '2016-05-10', 'vote_count' => 7],
            ['id' => 329865, 'title' => 'Arrival', 'release_date' => '2016-11-10', 'vote_count' => 20139, 'poster_path' => '/arrival.jpg'],
        ],
        'Dune' => [
            ['id' => 693134, 'title' => 'Dune: Part Two', 'release_date' => '2024-02-27', 'vote_count' => 8922],
            ['id' => 438631, 'title' => 'Dune', 'release_date' => '2021-09-15', 'vote_count' => 15908],
            ['id' => 841, 'title' => 'Dune', 'release_date' => '1984-12-14', 'vote_count' => 3606],
        ],
    ];

    private const TV = [
        'Severance' => [
            ['id' => 95396, 'name' => 'Severance', 'first_air_date' => '2022-02-17', 'vote_count' => 3099, 'poster_path' => '/series.jpg', 'overview' => 'Mark leads a team of office workers…'],
        ],
        'Spirited Away' => [
            ['id' => 282990, 'name' => 'Spirited Away Flying to London', 'first_air_date' => '2024-08-17', 'vote_count' => 0],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['tmdb.read_token' => 'test-token']);

        // TMDB's search, run from the fixtures above. TMDB filters by year
        // when one is sent.
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $tv = str_contains($request->url(), '/search/tv');
            $results = ($tv ? self::TV : self::MOVIE)[$q['query']] ?? [];
            if ($year = $q['year'] ?? $q['first_air_date_year'] ?? null) {
                $results = array_values(array_filter($results,
                    fn ($r) => str_starts_with($r['release_date'] ?? $r['first_air_date'] ?? '', $year)));
            }

            return Http::response(['results' => $results]);
        });
    }

    private function enrich(string $pasted): LibraryItem
    {
        $host = Host::factory()->create();
        LibrarySource::import($host, LibrarySource::parsePasted($pasted));
        $this->artisan('tmdb:enrich')->assertSuccessful();

        return $host->libraryItems()->firstOrFail();
    }

    public function test_a_series_is_the_series_not_a_film_of_the_same_name(): void
    {
        $item = $this->enrich('Severance, series');

        $this->assertSame('95396', $item->tmdb_id);
        $this->assertStringEndsWith('/series.jpg', $item->poster_url);
    }

    public function test_the_exact_title_beats_a_stage_recording_ranked_first(): void
    {
        $item = $this->enrich('Spirited Away');

        $this->assertSame('129', $item->tmdb_id);
        $this->assertStringEndsWith('/ghibli.jpg', $item->poster_url);
    }

    public function test_the_exact_title_beats_one_with_the(): void
    {
        $this->assertSame('329865', $this->enrich('Arrival (2016)')->tmdb_id);
    }

    public function test_the_year_picks_between_films_of_the_same_name(): void
    {
        $this->assertSame('438631', $this->enrich('Dune')->tmdb_id);
        $this->assertSame('841', $this->enrich('Dune (1984)')->tmdb_id);
    }

    public function test_the_type_asked_for_wins_when_both_have_the_title(): void
    {
        // A film was asked for, so the 2006 film, not the better-known series.
        $this->assertSame('5072', $this->enrich('Severance')->tmdb_id);
    }

    public function test_no_exact_match_in_the_type_asked_for_falls_back_to_the_other(): void
    {
        // Typed as a series, but only the film is called that.
        $this->assertSame('129', $this->enrich('Spirited Away, series')->tmdb_id);
    }
}
