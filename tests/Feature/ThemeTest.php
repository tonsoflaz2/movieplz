<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Host;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each person picks a color theme: eight, four dark and four light. Screens
 * are written in Classic's colors and recolored on the way out
 * (App\Support\Theme, App\Http\Middleware\ApplyTheme).
 */
class ThemeTest extends TestCase
{
    use RefreshDatabase;

    private Host $library;

    private Client $me;

    private Client $friend;

    protected function setUp(): void
    {
        parent::setUp();
        $this->library = Host::factory()->create(['name' => "Christian's library"]);
        $this->me = Client::factory()->host()->create(['host_id' => $this->library->id, 'display_name' => 'Christian']);
        $this->friend = Client::factory()->create(['host_id' => $this->library->id, 'display_name' => 'Sam']);
    }

    private function as(Client $client): static
    {
        return $this->withSession(['client_id' => $client->id]);
    }

    public function test_there_are_eight_themes_four_dark_and_four_light(): void
    {
        $this->assertCount(8, Theme::THEMES);
        $this->assertSame(4, collect(Theme::THEMES)->where('dark', true)->count());
        $this->assertSame(4, collect(Theme::THEMES)->where('dark', false)->count());
        foreach (Theme::THEMES as $key => $t) {
            foreach (['name', 'bg', 'bar', 'input', 'barText', 'navOff', 'text', 'textHex', 'muted', 'faint', 'outline', 'fill', 'overlay', 'success', 'successStrong', 'successBg', 'mode'] as $role) {
                $this->assertArrayHasKey($role, $t, "{$key} has {$role}");
            }
            $this->assertSame($t['dark'] ? 'dark' : 'light', $t['mode'], $key);
        }
    }

    public function test_classic_screens_go_out_exactly_as_before(): void
    {
        $ham = '<block style="bg-black bg-opacity-10 text-red-100"><icon name="mail red-100"></icon></block>';

        $this->assertSame($ham, Theme::apply($ham, null));
        $this->assertSame($ham, Theme::apply($ham, 'classic'));
        $this->assertSame($ham, Theme::apply($ham, 'no-such-theme'));
    }

    public function test_a_theme_swaps_classic_colors_in_color_attributes_only(): void
    {
        $ham = '<config dark-mode="dark" bg_color="#DC2626" primary_color="#991B1B" default_text_color="#FFFFFF" selected_color="#FFFFFF" unselected_color="#FECACA"></config>'
            .'<app-bar title="MoviePlz" bg_color="#991B1B" text_color="#FFFFFF"/>'
            .'<block style="bg-black bg-opacity-10 p-15"><p style="text-red-100 font-sm">From #FECACA text-white</p>'
            .'<icon name="check_circle green-300"></icon><icon name="error white"></icon>'
            .'<search-bar style="bg-#B91C1C text-white"/>'
            .'<button style="filled #1F2937 sm">Go</button><button style="outlined #FECACA sm">No</button>'
            .'<button style="filled green sm">Added</button><input name="red-100" value="text-white"/></block>';

        $out = Theme::apply($ham, 'paper');

        $this->assertStringContainsString('dark-mode="light"', $out);
        $this->assertStringContainsString('bg_color="#FAFAFA"', $out);
        $this->assertStringContainsString('primary_color="#DC2626"', $out);
        $this->assertStringContainsString('default_text_color="#111111"', $out);
        $this->assertStringContainsString('selected_color="#FFFFFF"', $out, 'tabs sit on the bar: bar text color');
        $this->assertStringContainsString('unselected_color="#FECACA"', $out);
        $this->assertStringContainsString('<app-bar title="MoviePlz" bg_color="#DC2626" text_color="#FFFFFF"/>', $out);
        $this->assertStringContainsString('style="bg-black bg-opacity-10 p-15"', $out);
        $this->assertStringContainsString('style="text-red-900 font-sm"', $out);
        $this->assertStringContainsString('<icon name="check_circle green-800">', $out);
        $this->assertStringContainsString('<icon name="error black">', $out);
        $this->assertStringContainsString('style="bg-#E5E7EB text-black"', $out);
        $this->assertStringContainsString('style="outlined #DC2626 sm"', $out);
        $this->assertStringContainsString('style="filled green sm"', $out, 'plain color names stay');
        // Text and other attributes are never touched.
        $this->assertStringContainsString('From #FECACA text-white</p>', $out);
        $this->assertStringContainsString('<input name="red-100" value="text-white"/>', $out);
    }

    public function test_dark_themes_on_near_black_tint_cards_with_white(): void
    {
        $out = Theme::apply('<block style="bg-black bg-opacity-10"></block>', 'midnight');

        $this->assertSame('<block style="bg-white bg-opacity-10"></block>', $out);
    }

    public function test_lowercase_hex_is_left_alone(): void
    {
        $ham = '<block style="bg-#dc2626 border-#fecaca"></block>';

        $this->assertSame($ham, Theme::apply($ham, 'sky'));
    }

    public function test_the_signed_in_persons_theme_colors_their_screens(): void
    {
        $this->me->forceFill(['theme' => 'cinema'])->save();

        $this->as($this->me)->get('/app.ham')->assertOk()
            ->assertSee('<screen bg_color="#0A0A0A">', false)
            ->assertSee('primary_color="#000000"', false)
            ->assertDontSee('#DC2626', false);
        $this->as($this->me)->get('/host/requests.ham')->assertOk()->assertDontSee('text-red-100', false);

        // Sam hasn't picked one: Classic.
        $this->as($this->friend)->get('/app.ham')->assertOk()->assertSee('<screen bg_color="#DC2626">', false);
    }

    public function test_the_website_pages_are_never_themed(): void
    {
        $this->me->forceFill(['theme' => 'paper'])->save();

        $classic = $this->get('/support')->getContent();
        $themed = $this->as($this->me)->get('/support')->getContent();

        $this->assertSame(preg_replace('/name="_token" value="[^"]*"/', '', $classic), preg_replace('/name="_token" value="[^"]*"/', '', $themed));
    }

    public function test_the_home_screens_link_to_the_picker(): void
    {
        $this->as($this->me)->get('/host/home.ham')->assertOk()
            ->assertSee('action="navigate:/theme.ham"', false)->assertSee('Theme: Classic', false);
        $this->friend->forceFill(['theme' => 'mint'])->save();
        $this->as($this->friend)->get('/home.ham')->assertOk()
            ->assertSee('action="navigate:/theme.ham"', false)->assertSee('Theme: Mint', false);
    }

    public function test_the_picker_lists_all_eight_in_their_own_colors(): void
    {
        $this->me->forceFill(['theme' => 'sky'])->save();

        $response = $this->as($this->me)->get('/theme.ham')->assertOk()
            ->assertSeeInOrder(['Dark', 'Classic', 'Midnight', 'Cinema', 'Plum', 'Light', 'Paper', 'Sky', 'Mint', 'Popcorn'], false)
            ->assertSee('action="/theme?name=popcorn"', false)
            ->assertSee('In use', false);

        // Classic's card keeps its red under the Sky theme: swatches are lowercase hex.
        $response->assertSee('bg-#dc2626', false);
        $this->assertSame(1, substr_count($response->getContent(), 'In use'));
    }

    public function test_picking_a_theme_saves_it_and_restarts_the_app(): void
    {
        $this->as($this->friend)->post('/theme?name=plum')->assertOk()
            ->assertSee('id="theme-picker"', false)
            ->assertSee('<block action="app:/app.ham" trigger="load"></block>', false)
            ->assertSee('Switching MoviePlz to Plum', false);

        $this->assertSame('plum', $this->friend->fresh()->theme);
    }

    public function test_picking_classic_clears_the_choice(): void
    {
        $this->me->forceFill(['theme' => 'mint'])->save();

        $this->as($this->me)->post('/theme?name=classic')->assertOk();

        $this->assertNull($this->me->fresh()->theme);
    }

    public function test_an_unknown_theme_is_refused(): void
    {
        $this->as($this->me)->post('/theme?name=hotdog')->assertOk()->assertSee("That theme isn't one of the choices.");

        $this->assertNull($this->me->fresh()->theme);
    }

    public function test_signed_out_there_is_no_picker(): void
    {
        $this->get('/theme.ham')->assertDontSee('theme-picker', false);
        $this->post('/theme?name=plum')->assertDontSee('Switching', false);
    }
}
