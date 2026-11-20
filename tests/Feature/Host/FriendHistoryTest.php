<?php

namespace Tests\Feature\Host;

use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The host taps a friend in Clients to see all their suggestions, grouped
 * New, In progress, Added, Declined, and can answer pending ones there.
 */
class FriendHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Client $me;

    private Client $laurie;

    private Client $sam;

    protected function setUp(): void
    {
        parent::setUp();
        $library = Host::factory()->create();
        $this->me = Client::factory()->host()->create(['host_id' => $library->id, 'display_name' => 'Christian']);
        $this->laurie = Client::factory()->create(['host_id' => $library->id, 'display_name' => 'Laurie']);
        $this->sam = Client::factory()->create(['host_id' => $library->id, 'display_name' => 'Sam']);
    }

    private function suggest(Client $from, string $title, array $attrs = []): MediaRequest
    {
        return MediaRequest::factory()->create($attrs + ['host_id' => $from->host_id, 'client_id' => $from->id,
            'title' => $title, 'status' => 'pending', 'notes' => null, 'year' => null, 'type' => 'film']);
    }

    private function asHost(): static
    {
        return $this->withSession(['client_id' => $this->me->id]);
    }

    public function test_each_friend_in_clients_opens_their_history_and_shows_a_count(): void
    {
        $this->suggest($this->laurie, 'Dune');
        $this->suggest($this->laurie, 'Heat');

        $this->asHost()->get('/host/clients.ham')->assertOk()
            ->assertSee('action="navigate:/host/clients/'.$this->laurie->id.'.ham"', false)
            ->assertSee('2 suggestions', false)
            ->assertSee('0 suggestions', false);
    }

    public function test_the_history_groups_one_friends_suggestions_by_status(): void
    {
        $this->suggest($this->laurie, 'Arrival');
        $this->suggest($this->laurie, 'Dune', ['up_next_at' => now()]);
        $item = LibraryItem::factory()->create(['host_id' => $this->me->host_id, 'title' => 'Heat']);
        $this->suggest($this->laurie, 'Heat', ['status' => 'fulfilled', 'fulfilled_at' => now(), 'fulfilled_library_item_id' => $item->id]);
        $this->suggest($this->laurie, 'Cats', ['status' => 'declined', 'host_reply' => 'Never.']);
        $this->suggest($this->sam, 'Alien');

        $this->asHost()->get("/host/clients/{$this->laurie->id}.ham")->assertOk()
            ->assertSee('<h1 style="font-2xl font-bold mb-5">Laurie</h1>', false)
            ->assertSee('4 suggestions · 1 added · 1 declined', false)
            ->assertSeeInOrder(['New · 1', 'Arrival', 'In progress · 1', 'Dune', 'Added · 1', 'Heat', 'Declined · 1', 'Cats', 'You replied: &quot;Never.&quot;'], false)
            ->assertDontSee('Alien', false);
    }

    public function test_pending_suggestions_can_be_answered_from_the_history(): void
    {
        $s = $this->suggest($this->laurie, 'Arrival');

        $this->asHost()->get("/host/clients/{$this->laurie->id}.ham")
            ->assertSee('action="/host/requests/'.$s->id.'/up-next"', false)
            ->assertSee('target="#request-'.$s->id.'"', false);
    }

    public function test_the_history_uses_the_hosts_nickname(): void
    {
        $this->laurie->forceFill(['host_nickname' => 'Mom'])->save();
        $this->suggest($this->laurie, 'Dune');

        $this->asHost()->get("/host/clients/{$this->laurie->id}.ham")
            ->assertSee('>Mom</h1>', false)->assertSee('Calls themselves Laurie', false)->assertSee('From Mom', false);
    }

    public function test_a_friend_with_no_suggestions_says_so(): void
    {
        $this->asHost()->get("/host/clients/{$this->sam->id}.ham")->assertOk()
            ->assertSee("Sam hasn't suggested anything yet.", false);
    }

    public function test_a_revoked_friends_history_is_still_there(): void
    {
        $this->suggest($this->laurie, 'Dune');
        $this->laurie->forceFill(['revoked_at' => now()])->save();

        $this->asHost()->get("/host/clients/{$this->laurie->id}.ham")->assertOk()->assertSee('Dune', false);
    }

    public function test_only_the_host_sees_it_and_only_their_own_friends(): void
    {
        $this->suggest($this->laurie, 'Dune');
        $stranger = Client::factory()->create(['host_id' => Host::factory()->create()->id]);
        MediaRequest::factory()->create(['host_id' => $stranger->host_id, 'client_id' => $stranger->id, 'title' => 'Secret Film']);

        $this->withSession(['client_id' => $this->sam->id])->get("/host/clients/{$this->laurie->id}.ham")
            ->assertSee('Not authorized')->assertDontSee('Dune', false);
        $this->asHost()->get("/host/clients/{$stranger->id}.ham")->assertDontSee('Secret Film', false);
        $this->asHost()->get("/host/clients/{$this->me->id}.ham")->assertDontSee('<h1 style="font-2xl font-bold mb-5">Christian</h1>', false);
    }
}
