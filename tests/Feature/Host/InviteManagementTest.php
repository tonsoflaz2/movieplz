<?php

namespace Tests\Feature\Host;

use App\Models\Client;
use App\Models\Host;
use App\Models\Invite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_generates_new_invite_code(): void
    {
        $host = Host::factory()->create();
        $hostClient = Client::factory()->host()->create(['host_id' => $host->id]);

        $before = Invite::count();

        $response = $this->withSession(['client_id' => $hostClient->id])
            ->post('/host/invite-code');

        $response->assertOk();
        $response->assertSee('Code for one friend', false);

        $this->assertSame($before + 1, Invite::count());
        $invite = Invite::latest('id')->first();
        $this->assertFalse($invite->grants_host);
        $this->assertSame($host->id, $invite->host_id);
        $this->assertMatchesRegularExpression('/^\d{5}$/', $invite->code);
        $this->assertTrue($invite->expires_at->isFuture());
    }

    public function test_expired_invite_is_not_usable(): void
    {
        $host = Host::factory()->create();
        $invite = Invite::factory()->expired()->create(['host_id' => $host->id]);

        $this->assertFalse($invite->isUsable());
        $this->assertSame(0, Invite::usable()->where('code', $invite->code)->count());
    }

    public function test_redeemed_invite_is_not_usable(): void
    {
        $host = Host::factory()->create();
        $invite = Invite::factory()->redeemed()->create(['host_id' => $host->id]);

        $this->assertFalse($invite->isUsable());
    }

    public function test_host_mint_code_command_creates_bootstrap_invite(): void
    {
        $this->assertSame(0, Client::where('role', 'host')->count());

        $this->artisan('host:mint-code')->assertSuccessful();

        $this->assertSame(1, Invite::where('grants_host', true)->count());
        $invite = Invite::where('grants_host', true)->first();
        $this->assertNull($invite->host_id);
        $this->assertMatchesRegularExpression('/^\d{5}$/', $invite->code);
    }

    public function test_host_mint_code_command_errors_when_host_exists(): void
    {
        $host = Host::factory()->create();
        Client::factory()->host()->create(['host_id' => $host->id]);

        $this->artisan('host:mint-code')
            ->expectsOutputToContain('already been claimed')
            ->assertFailed();
    }

    public function test_invite_code_endpoint_requires_host_role(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->create(['host_id' => $host->id]);

        $response = $this->withSession(['client_id' => $client->id])
            ->post('/host/invite-code');

        $response->assertOk();
        $response->assertSee('Not authorized', false);
        $this->assertSame(0, Invite::count());
    }
}
