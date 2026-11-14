<?php

namespace Tests\Feature\Onboarding;

use App\Models\Client;
use App\Models\Host;
use App\Models\Invite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NameSubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_name_creates_client_and_sets_session(): void
    {
        $host = Host::factory()->create(['name' => 'Test lib']);
        $invite = Invite::factory()->create([
            'host_id' => $host->id,
            'code' => '33333',
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this
            ->withSession(['pending_invite_id' => $invite->id])
            ->post('/onboarding/name', ['display_name' => 'Alice']);

        $response->assertOk();
        $response->assertSee("You're in!", false);
        $response->assertSee('Test lib', false);

        $client = Client::query()->where('display_name', 'Alice')->firstOrFail();
        $this->assertSame($host->id, $client->host_id);
        $this->assertSame('client', $client->role);
        $this->assertSame($invite->id, $client->invite_id);
        $this->assertSame($client->id, session('client_id'));
        $this->assertNull(session('pending_invite_id'));

        $this->assertNotNull($invite->fresh()->redeemed_at);
        $this->assertSame($client->id, $invite->fresh()->redeemed_by_client_id);
    }

    public function test_grants_host_invite_creates_host_row_and_host_client(): void
    {
        $invite = Invite::factory()->hostBootstrap()->create([
            'code' => '44444',
            'expires_at' => now()->addMinutes(5),
        ]);
        $this->assertNull($invite->host_id);

        $response = $this
            ->withSession(['pending_invite_id' => $invite->id])
            ->post('/onboarding/name', ['display_name' => 'Chris']);

        $response->assertOk();

        $this->assertDatabaseCount('hosts', 1);
        $host = Host::query()->firstOrFail();
        $this->assertStringContainsString('Chris', $host->name);

        $client = Client::query()->where('display_name', 'Chris')->firstOrFail();
        $this->assertSame('host', $client->role);
        $this->assertSame($host->id, $client->host_id);
        $this->assertSame($client->id, session('client_id'));

        // Invite was also wired to the new host row.
        $this->assertSame($host->id, $invite->fresh()->host_id);
    }

    public function test_missing_name_returns_name_form_with_error(): void
    {
        $host = Host::factory()->create();
        $invite = Invite::factory()->create(['host_id' => $host->id]);

        $response = $this
            ->withSession(['pending_invite_id' => $invite->id])
            ->post('/onboarding/name', ['display_name' => '']);

        $response->assertOk();
        $response->assertSee('Enter your name', false);
        $this->assertNull(session('client_id'));
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_missing_pending_invite_bounces_back_to_invite_form(): void
    {
        $response = $this->post('/onboarding/name', ['display_name' => 'Alice']);

        $response->assertOk();
        $response->assertSee('Enter your code again', false);
    }

    public function test_overlong_name_returns_error(): void
    {
        $host = Host::factory()->create();
        $invite = Invite::factory()->create(['host_id' => $host->id]);

        $response = $this
            ->withSession(['pending_invite_id' => $invite->id])
            ->post('/onboarding/name', ['display_name' => str_repeat('x', 41)]);

        $response->assertOk();
        $response->assertSee('40 characters', false);
    }

    public function test_grants_host_errors_if_host_already_exists(): void
    {
        // Pre-existing host and host client.
        $existingHost = Host::factory()->create();
        Client::factory()->host()->create(['host_id' => $existingHost->id]);

        // Try to redeem a host-bootstrap invite.
        $invite = Invite::factory()->hostBootstrap()->create();

        $response = $this
            ->withSession(['pending_invite_id' => $invite->id])
            ->post('/onboarding/name', ['display_name' => 'Chris']);

        // Global exception renderer catches the RuntimeException and
        // returns the 200 HAM error partial.
        $response->assertOk();
        $response->assertSee('host has already been claimed', false);

        $this->assertDatabaseCount('hosts', 1);
        $this->assertSame(1, Client::query()->where('role', 'host')->count());
    }
}
