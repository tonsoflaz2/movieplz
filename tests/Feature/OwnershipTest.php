<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Host;
use App\Models\Invite;
use App\Models\MediaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_sees_own_request_confirmation(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->create(['host_id' => $host->id]);
        $req = MediaRequest::factory()->create(['host_id' => $host->id, 'client_id' => $client->id]);

        $this->withSession(['client_id' => $client->id])
            ->get("/requests/confirmed/{$req->id}.ham")
            ->assertOk()
            ->assertSee($req->title, false);
    }

    public function test_client_cannot_see_another_clients_request_confirmation(): void
    {
        $host = Host::factory()->create();
        $owner = Client::factory()->create(['host_id' => $host->id]);
        $other = Client::factory()->create(['host_id' => $host->id]);
        $req = MediaRequest::factory()->create(['host_id' => $host->id, 'client_id' => $owner->id]);

        $this->withSession(['client_id' => $other->id])
            ->get("/requests/confirmed/{$req->id}.ham")
            ->assertDontSee($req->title, false);
    }

    public function test_host_cannot_read_another_hosts_invite_code(): void
    {
        $mine = Host::factory()->create();
        $theirs = Host::factory()->create();
        $me = Client::factory()->host()->create(['host_id' => $mine->id]);
        $invite = Invite::factory()->create([
            'host_id' => $theirs->id,
            'code' => '86420',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withSession(['client_id' => $me->id])
            ->get("/host/invite-status/{$invite->id}.ham")
            ->assertDontSee('Code for one friend', false);
    }
}
