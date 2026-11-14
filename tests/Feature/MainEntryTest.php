<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Host;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MainEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_ham_without_session_shows_onboarding(): void
    {
        $response = $this->get('/app.ham');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('Welcome to MoviePlz', false);
        $response->assertSee('I was invited', false);
        $response->assertDontSee('Hi,', false);
    }

    public function test_main_ham_with_valid_client_session_shows_client_home(): void
    {
        $host = Host::factory()->create(['name' => 'Test library']);
        $client = Client::factory()->create([
            'host_id' => $host->id,
            'display_name' => 'TestUser',
            'role' => 'client',
        ]);

        $response = $this->withSession(['client_id' => $client->id])->get('/app.ham');

        $response->assertOk();
        $response->assertSee('Hi, TestUser', false);
        $response->assertSee('Test library', false);
        $response->assertSee('bottom-nav-item label="Home" icon="home active:fill active:700" action="/home.ham"', false);
        $response->assertDontSee('Welcome to MoviePlz', false);
    }

    public function test_main_ham_with_host_session_shows_host_home(): void
    {
        $host = Host::factory()->create(['name' => 'Host lib']);
        $hostClient = Client::factory()->host()->create(['host_id' => $host->id]);

        $response = $this->withSession(['client_id' => $hostClient->id])->get('/app.ham');

        $response->assertOk();
        $response->assertSee('Host lib', false);
        $response->assertSee('bottom-nav-item label="Suggestions" icon="inbox active:fill active:700" action="/host/requests.ham"', false);
    }

    public function test_main_ham_with_revoked_client_session_shows_onboarding(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->revoked()->create(['host_id' => $host->id]);

        $response = $this->withSession(['client_id' => $client->id])->get('/app.ham');

        $response->assertOk();
        $response->assertSee('Welcome to MoviePlz', false);
    }

    public function test_main_ham_with_stale_client_id_shows_onboarding(): void
    {
        $response = $this->withSession(['client_id' => 999999])->get('/app.ham');

        $response->assertOk();
        $response->assertSee('Welcome to MoviePlz', false);
    }

    public function test_the_engine_splash_is_the_launch_screens_red(): void
    {
        // Between the native launch screen and this document the engine
        // paints <config splash_color>; it must be hype.toml's launch red.
        preg_match('/^splash_color\s*=\s*"(#[0-9A-Fa-f]{6})"/m', file_get_contents(base_path('hype.toml')), $m);
        $this->assertSame('#F00709', $m[1] ?? null);

        $this->get('/app.ham')->assertOk()
            ->assertSee('splash_color="'.$m[1].'"', false)
            ->assertSee('bg_color="#DC2626"', false);
    }
}
