<?php

namespace Tests\Feature\Host;

use App\Models\Client;
use App\Models\Host;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A library's name: whatever the host types at setup, "{name}'s library"
 * when they leave it blank, and renamed any time from the dashboard.
 */
class LibraryNameTest extends TestCase
{
    use RefreshDatabase;

    private function host(string $name = "Christian's library"): Client
    {
        $library = Host::factory()->create(['name' => $name]);

        return Client::factory()->host()->create(['host_id' => $library->id, 'display_name' => 'Christian']);
    }

    public function test_setup_offers_an_optional_library_name(): void
    {
        $this->get('/onboarding/host-setup.ham')->assertOk()
            ->assertSee('name="library_name"', false)
            ->assertSee('Library name (optional)', false);
    }

    public function test_setup_uses_the_typed_library_name(): void
    {
        $this->post('/onboarding/host-setup', ['display_name' => 'Christian', 'library_name' => '  Movie   Night  '])
            ->assertOk()->assertSee('Movie Night is ready', false);

        $this->assertSame('Movie Night', Host::sole()->name);
    }

    public function test_setup_falls_back_to_the_hosts_name(): void
    {
        $this->post('/onboarding/host-setup', ['display_name' => 'Christian', 'library_name' => ' '])->assertOk();

        $this->assertSame("Christian's library", Host::sole()->name);
    }

    public function test_setup_refuses_a_name_that_is_too_long_and_keeps_what_was_typed(): void
    {
        $this->post('/onboarding/host-setup', ['display_name' => 'Christian', 'library_name' => str_repeat('a', 61)])
            ->assertOk()->assertSee('Library names can be at most 60 characters.', false)
            ->assertSee('value="Christian"', false);

        $this->assertSame(0, Host::count());
    }

    public function test_the_dashboard_shows_the_name_with_rename(): void
    {
        $me = $this->host();

        $this->withSession(['client_id' => $me->id])->get('/host/home.ham')->assertOk()
            ->assertSee('id="library-name"', false)
            ->assertSee('Christian&#039;s library', false)
            ->assertSee('action="/host/library-name/edit.ham"', false);
    }

    public function test_rename_opens_the_field_filled_in(): void
    {
        $me = $this->host();

        $this->withSession(['client_id' => $me->id])->get('/host/library-name/edit.ham')->assertOk()
            ->assertSee('id="library-name"', false)
            ->assertSee('value="Christian&#039;s library"', false)
            ->assertSee('action="/host/library-name"', false);
    }

    public function test_the_host_renames_the_library_and_friends_see_it(): void
    {
        $me = $this->host();
        $friend = Client::factory()->create(['host_id' => $me->host_id]);

        $this->withSession(['client_id' => $me->id])->post('/host/library-name', ['name' => 'The Z Screening Room'])
            ->assertOk()->assertSee('The Z Screening Room', false)->assertSee('Rename', false);

        $this->assertSame('The Z Screening Room', $me->host->fresh()->name);
        $this->withSession(['client_id' => $friend->id])->get('/home.ham')->assertSee('The Z Screening Room', false);
    }

    public function test_a_blank_or_too_long_name_is_refused(): void
    {
        $me = $this->host();

        $this->withSession(['client_id' => $me->id])->post('/host/library-name', ['name' => '   '])
            ->assertOk()->assertSee('Give your library a name.', false);
        $this->withSession(['client_id' => $me->id])->post('/host/library-name', ['name' => str_repeat('b', 61)])
            ->assertOk()->assertSee('at most 60 characters', false)->assertSee('value="'.str_repeat('b', 61).'"', false);

        $this->assertSame("Christian's library", $me->host->fresh()->name);
    }

    public function test_cancel_shows_the_heading_again(): void
    {
        $me = $this->host('Movie Night');

        $this->withSession(['client_id' => $me->id])->get('/host/library-name.ham')->assertOk()
            ->assertSee('Movie Night', false)->assertDontSee('name="name"', false);
    }

    public function test_a_friend_cannot_rename_the_library(): void
    {
        $me = $this->host();
        $friend = Client::factory()->create(['host_id' => $me->host_id]);

        $this->withSession(['client_id' => $friend->id])->post('/host/library-name', ['name' => 'Mine now'])
            ->assertSee('Not authorized');

        $this->assertSame("Christian's library", $me->host->fresh()->name);
    }
}
