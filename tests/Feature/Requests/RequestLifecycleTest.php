<?php

namespace Tests\Feature\Requests;

use App\Models\Client;
use App\Models\Host;
use App\Models\MediaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_creates_pending_request(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->create(['host_id' => $host->id]);

        $response = $this->withSession(['client_id' => $client->id])
            ->post('/requests', [
                'title' => 'Poor Things',
                'year' => 2023,
                'type' => 'film',
                'notes' => 'loved the book',
            ]);

        $response->assertOk();
        $response->assertSee('navigate:/requests/confirmed/', false);

        $this->assertDatabaseHas('requests', [
            'client_id' => $client->id,
            'host_id' => $host->id,
            'title' => 'Poor Things',
            'year' => 2023,
            'type' => 'film',
            'notes' => 'loved the book',
            'status' => 'pending',
        ]);
    }

    public function test_empty_title_returns_form_with_error(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->create(['host_id' => $host->id]);

        $response = $this->withSession(['client_id' => $client->id])
            ->post('/requests', ['title' => '']);

        $response->assertOk();
        $response->assertSee('Tell us what you want to watch', false);
        $this->assertDatabaseCount('requests', 0);
    }

    public function test_host_fulfills_pending_request(): void
    {
        $host = Host::factory()->create();
        $hostClient = Client::factory()->host()->create(['host_id' => $host->id]);
        $client = Client::factory()->create(['host_id' => $host->id]);
        $req = MediaRequest::factory()->create([
            'host_id' => $host->id,
            'client_id' => $client->id,
            'status' => 'pending',
        ]);

        $response = $this->withSession(['client_id' => $hostClient->id])
            ->post("/host/requests/{$req->id}/fulfill");

        $response->assertOk();
        $response->assertSee('in your library', false);

        $fresh = $req->fresh();
        $this->assertSame('fulfilled', $fresh->status);
        $this->assertNotNull($fresh->fulfilled_at);
        $this->assertNotNull($fresh->fulfilled_library_item_id);
    }

    public function test_host_declines_pending_request(): void
    {
        $host = Host::factory()->create();
        $hostClient = Client::factory()->host()->create(['host_id' => $host->id]);
        $client = Client::factory()->create(['host_id' => $host->id]);
        $req = MediaRequest::factory()->create([
            'host_id' => $host->id,
            'client_id' => $client->id,
            'status' => 'pending',
        ]);

        $this->withSession(['client_id' => $hostClient->id])
            ->post("/host/requests/{$req->id}/decline")
            ->assertOk();

        $this->assertSame('declined', $req->fresh()->status);
    }

    public function test_visiting_requests_index_marks_fulfilled_as_seen(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->create(['host_id' => $host->id]);
        $req = MediaRequest::factory()->fulfilled()->create([
            'host_id' => $host->id,
            'client_id' => $client->id,
            'seen_by_client_at' => null,
        ]);

        $this->withSession(['client_id' => $client->id])
            ->get('/requests.ham')
            ->assertOk();

        $this->assertNotNull($req->fresh()->seen_by_client_at);
    }

    public function test_non_host_client_cannot_fulfill(): void
    {
        $host = Host::factory()->create();
        $client = Client::factory()->create(['host_id' => $host->id]);
        $req = MediaRequest::factory()->create([
            'host_id' => $host->id,
            'client_id' => $client->id,
            'status' => 'pending',
        ]);

        $response = $this->withSession(['client_id' => $client->id])
            ->post("/host/requests/{$req->id}/fulfill");

        $response->assertOk();
        $response->assertSee('Not authorized', false);
        $this->assertSame('pending', $req->fresh()->status);
    }
}
