<?php

namespace Tests\Feature\Host;

use App\Models\Client;
use App\Models\Host;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Dashboard's Library box opens the Library tab: the app restarts on
 * /library.ham with that tab selected.
 */
class DashboardLibraryLinkTest extends TestCase
{
    use RefreshDatabase;

    private Client $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->me = Client::factory()->host()->create(['host_id' => Host::factory()->create()->id]);
    }

    public function test_the_library_box_opens_the_library_tab(): void
    {
        $ham = $this->withSession(['client_id' => $this->me->id])->get('/host/home.ham')->assertOk()->getContent();

        $this->assertStringContainsString('<block action="app:/app.ham?open=/library.ham">', $ham);
        // "Add titles" is outside the tappable part.
        $tappable = substr($ham, strpos($ham, 'app:/app.ham?open=/library.ham'));
        $this->assertLessThan(strpos($tappable, 'navigate:/host/library-source.ham'), strpos($tappable, '</block>'));
    }

    public function test_opening_on_the_library_selects_its_tab(): void
    {
        $this->withSession(['client_id' => $this->me->id])->get('/app.ham?open=/library.ham')->assertOk()
            ->assertSee('<main id="main" action="/library.ham"', false)
            ->assertSee('label="Library" icon="movie active:fill active:700" action="/library.ham" selected="true"', false)
            ->assertSee('label="Dashboard" icon="home active:fill active:700" action="/host/home.ham" selected="false"', false);
    }
}
