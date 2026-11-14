<?php

namespace Tests\Feature\Onboarding;

use App\Models\Client;
use App\Models\Host;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoked_client_hitting_authed_route_gets_session_expired(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->create(['host_id' => $host->id]);

        // First, we can access home normally.
        $this->withSession(['client_id' => $client->id])
            ->get('/home.ham')
            ->assertOk()
            ->assertSee('Hi, '.$client->display_name, false);

        // Host revokes.
        $client->forceFill(['revoked_at' => now()])->save();

        // Next request sees the session-expired HAM partial.
        $response = $this->withSession(['client_id' => $client->id])->get('/home.ham');
        $response->assertOk();
        $response->assertSee('Your session has ended.', false);
        $response->assertSee('app:/app.ham', false);
    }

    public function test_revoked_client_main_ham_falls_back_to_onboarding(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->revoked()->create(['host_id' => $host->id]);

        $response = $this->withSession(['client_id' => $client->id])->get('/app.ham');

        $response->assertOk();
        $response->assertSee('Welcome to MoviePlz', false);
    }
}
