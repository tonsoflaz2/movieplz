<?php

namespace Tests\Feature\Requests;

use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The host deletes a suggestion (a duplicate, one sent by mistake), whatever
 * its status. It asks first, leaves the friend's list too, and tells nobody.
 */
class DeleteSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private Client $me;

    private Client $friend;

    protected function setUp(): void
    {
        parent::setUp();
        $library = Host::factory()->create();
        $this->me = Client::factory()->host()->create(['host_id' => $library->id, 'display_name' => 'Christian']);
        $this->friend = Client::factory()->create(['host_id' => $library->id, 'display_name' => 'Sam',
            'push_token' => str_repeat('a', 64), 'push_platform' => 'ios']);
    }

    protected function tearDown(): void
    {
        PushService::$apnsTransport = null;
        parent::tearDown();
    }

    private function suggest(string $title, array $attrs = []): MediaRequest
    {
        return MediaRequest::factory()->create($attrs + ['host_id' => $this->me->host_id, 'client_id' => $this->friend->id,
            'title' => $title, 'status' => 'pending', 'notes' => null]);
    }

    private function asHost(): static
    {
        return $this->withSession(['client_id' => $this->me->id]);
    }

    public function test_every_suggestion_row_offers_delete_that_asks_first(): void
    {
        $new = $this->suggest('Dune');
        $declined = $this->suggest('Cats', ['status' => 'declined']);

        $this->asHost()->get('/host/requests.ham')->assertOk()
            ->assertSee('@click="$deleting = true"', false)
            ->assertSee('@show="$deleting"', false)
            ->assertSee("Delete Sam's suggestion of Dune?", false)
            ->assertSee('action="/host/requests/'.$new->id.'/delete"', false)
            ->assertDontSee('confirm="', false);
        $this->asHost()->get('/host/requests.ham?status=passed')->assertSee('action="/host/requests/'.$declined->id.'/delete"', false);
    }

    public function test_the_host_deletes_a_duplicate_and_it_leaves_the_friends_list_without_a_notification(): void
    {
        $sent = 0;
        PushService::$apnsTransport = function () use (&$sent) {
            $sent++;

            return [200, '', 'ID'];
        };
        $this->suggest('Dune');
        $duplicate = $this->suggest('Dune');

        $this->asHost()->post("/host/requests/{$duplicate->id}/delete")->assertOk()
            ->assertSee('id="request-'.$duplicate->id.'"', false)
            ->assertSee("Deleted Sam's suggestion of Dune.", false);

        $this->assertSame(1, MediaRequest::count());
        $this->assertSame(0, $sent);
        $this->withSession(['client_id' => $this->friend->id])->get('/requests.ham')->assertSee('Waiting (1)', false);
    }

    public function test_deleting_an_added_suggestion_keeps_the_title_in_the_library(): void
    {
        $item = LibraryItem::factory()->create(['host_id' => $this->me->host_id, 'title' => 'Heat']);
        $added = $this->suggest('Heat', ['status' => 'fulfilled', 'fulfilled_at' => now(), 'fulfilled_library_item_id' => $item->id]);

        $this->asHost()->post("/host/requests/{$added->id}/delete")->assertOk();

        $this->assertSame(0, MediaRequest::count());
        $this->assertTrue(LibraryItem::whereKey($item->id)->exists());
    }

    public function test_the_message_uses_the_hosts_nickname(): void
    {
        $this->friend->forceFill(['host_nickname' => 'Mom'])->save();
        $s = $this->suggest('Dune');

        $this->asHost()->post("/host/requests/{$s->id}/delete")->assertSee("Deleted Mom's suggestion of Dune.", false);
    }

    public function test_a_friend_cant_delete_and_a_host_cant_delete_another_librarys(): void
    {
        $mine = $this->suggest('Dune');
        $stranger = Client::factory()->create(['host_id' => Host::factory()->create()->id]);
        $theirs = MediaRequest::factory()->create(['host_id' => $stranger->host_id, 'client_id' => $stranger->id]);

        $this->withSession(['client_id' => $this->friend->id])->post("/host/requests/{$mine->id}/delete")->assertSee('Not authorized');
        $this->asHost()->post("/host/requests/{$theirs->id}/delete");

        $this->assertTrue(MediaRequest::whereKey($mine->id)->exists());
        $this->assertTrue(MediaRequest::whereKey($theirs->id)->exists());
    }
}
