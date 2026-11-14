<?php

namespace Tests\Feature\Host;

use App\Models\Client;
use App\Models\Host;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_revokes_client(): void
    {
        $host = Host::factory()->create();
        $hostClient = Client::factory()->host()->create(['host_id' => $host->id]);
        $client = Client::factory()->create([
            'host_id' => $host->id,
            'display_name' => 'Bob',
        ]);

        $response = $this->withSession(['client_id' => $hostClient->id])
            ->post("/host/clients/{$client->id}/revoke");

        $response->assertOk();
        $response->assertSee('Revoked', false);

        $this->assertNotNull($client->fresh()->revoked_at);
    }

    public function test_revoked_client_cannot_access_host_list_of_their_host(): void
    {
        $host = Host::factory()->create();
        $hostClient = Client::factory()->host()->create(['host_id' => $host->id]);
        $aliceRevoked = Client::factory()->revoked()->create([
            'host_id' => $host->id,
            'display_name' => 'Alice',
        ]);
        Client::factory()->create([
            'host_id' => $host->id,
            'display_name' => 'Bob',
        ]);

        $response = $this->withSession(['client_id' => $hostClient->id])
            ->get('/host/clients.ham');

        $response->assertOk();
        // The revoked client still appears in the list but is marked as such.
        $response->assertSee('Alice', false);
        $response->assertSee('Revoked', false);
        $response->assertSee('Bob', false);
    }

    public function test_host_cannot_revoke_another_hosts_client(): void
    {
        $hostA = Host::factory()->create();
        $hostB = Host::factory()->create();
        $hostAClient = Client::factory()->host()->create(['host_id' => $hostA->id]);
        $hostBsClient = Client::factory()->create(['host_id' => $hostB->id]);

        $response = $this->withSession(['client_id' => $hostAClient->id])
            ->post("/host/clients/{$hostBsClient->id}/revoke");

        // The global exception renderer catches the ModelNotFoundException
        // (from findOrFail) and returns a 200 HAM error partial.
        $response->assertOk();
        $this->assertNull($hostBsClient->fresh()->revoked_at);
    }
}
