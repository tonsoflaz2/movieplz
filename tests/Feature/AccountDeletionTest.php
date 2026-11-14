<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Host;
use App\Models\Invite;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_friend_can_leave_and_their_requests_go_with_them(): void
    {
        $host = Host::factory()->create();
        $friend = Client::factory()->create(['host_id' => $host->id]);
        MediaRequest::factory()->create(['host_id' => $host->id, 'client_id' => $friend->id]);

        $this->withSession(['client_id' => $friend->id])
            ->post('/account/leave')
            ->assertOk()
            ->assertSee("You've left")
            ->assertSessionMissing('client_id');

        $this->assertModelMissing($friend);
        $this->assertSame(0, MediaRequest::count());
        $this->assertModelExists($host);
    }

    public function test_a_host_can_delete_their_library_and_everything_in_it(): void
    {
        $host = Host::factory()->create();
        $me = Client::factory()->host()->create(['host_id' => $host->id]);
        $friend = Client::factory()->create(['host_id' => $host->id]);
        MediaRequest::factory()->create(['host_id' => $host->id, 'client_id' => $friend->id]);
        LibraryItem::factory()->create(['host_id' => $host->id]);
        Invite::factory()->create(['host_id' => $host->id, 'code' => '11111', 'expires_at' => now()->addMinutes(5)]);
        $otherHost = Host::factory()->create();
        LibraryItem::factory()->create(['host_id' => $otherHost->id]);

        $this->withSession(['client_id' => $me->id])
            ->post('/host/delete-library')
            ->assertOk()
            ->assertSee('Your library is deleted', false);

        $this->assertModelMissing($host);
        $this->assertSame(0, Client::count());
        $this->assertSame(0, MediaRequest::count());
        $this->assertSame(0, Invite::count());
        $this->assertSame(1, LibraryItem::count());
    }

    public function test_leaving_restarts_onto_the_welcome_screen_which_says_what_was_deleted(): void
    {
        $host = Host::factory()->create(['name' => 'Movie Night']);
        $friend = Client::factory()->create(['host_id' => $host->id, 'display_name' => 'Sam']);

        $this->withSession(['client_id' => $friend->id])
            ->post('/account/leave')
            ->assertSee('@on.load="app:/app.ham"', false);

        $this->get('/app.ham')
            ->assertSee('Welcome to MoviePlz')
            ->assertSee("You've left Movie Night")
            ->assertDontSee('Hi, Sam');

        // Once only.
        $this->get('/app.ham')->assertSee('Welcome to MoviePlz')->assertDontSee("You've left");
    }

    public function test_deleting_restarts_onto_the_welcome_screen_which_says_what_was_deleted(): void
    {
        $host = Host::factory()->create();
        $me = Client::factory()->host()->create(['host_id' => $host->id]);

        $this->withSession(['client_id' => $me->id])
            ->post('/host/delete-library')
            ->assertSee('@on.load="app:/app.ham"', false);

        $this->get('/app.ham')
            ->assertSee('Welcome to MoviePlz')
            ->assertSee('Your library is deleted', false);
    }

    public function test_the_delete_confirm_buttons_have_room_above_the_tab_bar(): void
    {
        $host = Host::factory()->create();
        $me = Client::factory()->host()->create(['host_id' => $host->id]);

        $this->withSession(['client_id' => $me->id])->get('/app.ham')
            ->assertSee('<block id="account-actions" var-leaving="false" style="mt-30 mb-150">', false);
    }

    public function test_a_friend_cannot_delete_the_hosts_library(): void
    {
        $host = Host::factory()->create();
        $friend = Client::factory()->create(['host_id' => $host->id]);

        $this->withSession(['client_id' => $friend->id])->post('/host/delete-library')->assertOk();

        $this->assertModelExists($host);
    }
}
