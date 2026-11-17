<?php

namespace Tests\Feature\Host;

use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The host renames a friend: a nickname only the host sees, on their
 * screens and in their notifications. The friend keeps their own name.
 */
class FriendNicknameTest extends TestCase
{
    use RefreshDatabase;

    private Client $me;

    private Client $friend;

    protected function setUp(): void
    {
        parent::setUp();
        $library = Host::factory()->create(['name' => "Christian's library"]);
        $this->me = Client::factory()->host()->create(['host_id' => $library->id, 'display_name' => 'Christian']);
        $this->friend = Client::factory()->create(['host_id' => $library->id, 'display_name' => 'Laurie']);
    }

    protected function tearDown(): void
    {
        PushService::$apnsTransport = null;
        parent::tearDown();
    }

    private function asHost(): static
    {
        return $this->withSession(['client_id' => $this->me->id]);
    }

    public function test_the_clients_list_offers_rename(): void
    {
        $this->asHost()->get('/host/clients.ham')->assertOk()
            ->assertSee('action="/host/clients/'.$this->friend->id.'/rename.ham"', false)
            ->assertSee('Rename', false);
    }

    public function test_rename_opens_the_field_with_their_current_name(): void
    {
        $this->asHost()->get("/host/clients/{$this->friend->id}/rename.ham")->assertOk()
            ->assertSee('id="client-'.$this->friend->id.'"', false)
            ->assertSee('value="Laurie"', false)
            ->assertSee('Only you see this. They call themselves Laurie', false);
    }

    public function test_the_host_renames_a_friend_and_only_the_host_sees_it(): void
    {
        $this->asHost()->post("/host/clients/{$this->friend->id}/rename", ['nickname' => '  Mom  '])->assertOk()
            ->assertSee('>Mom</p>', false)
            ->assertSee('Calls themselves Laurie', false);

        $this->friend->refresh();
        $this->assertSame(['Mom', 'Laurie'], [$this->friend->host_nickname, $this->friend->display_name]);
        $this->withSession(['client_id' => $this->friend->id])->get('/home.ham')->assertSee('Hi, Laurie', false)->assertDontSee('Mom', false);
    }

    public function test_the_nickname_shows_on_the_hosts_suggestions_and_tbw(): void
    {
        $this->friend->forceFill(['host_nickname' => 'Mom'])->save();
        MediaRequest::factory()->create(['host_id' => $this->me->host_id, 'client_id' => $this->friend->id, 'title' => 'Dune', 'notes' => null]);
        $added = MediaRequest::factory()->create(['host_id' => $this->me->host_id, 'client_id' => $this->friend->id, 'title' => 'Heat', 'notes' => null]);
        $this->asHost()->post("/host/requests/{$added->id}/fulfill")->assertOk();

        $this->asHost()->get('/host/requests.ham')->assertSee('From Mom', false)
            ->assertSee('Anything you want Mom to know', false)->assertDontSee('Laurie', false);
        $this->asHost()->get('/host/tbw.ham')->assertSee('Suggested by Mom', false);
        $this->asHost()->get('/host/requests/'.MediaRequest::where('title', 'Dune')->value('id').'/edit.ham')->assertSee('Edit Mom&#039;s suggestion', false);
    }

    public function test_the_hosts_notification_uses_the_nickname(): void
    {
        $this->friend->forceFill(['host_nickname' => 'Mom'])->save();
        $this->me->forceFill(['push_token' => str_repeat('a', 64), 'push_platform' => 'ios'])->save();
        $alerts = [];
        PushService::$apnsTransport = function ($token, $payload) use (&$alerts) {
            $alerts[] = json_decode($payload, true)['aps']['alert'];

            return [200, '', 'ID'];
        };

        $this->withSession(['client_id' => $this->friend->id])->post('/requests', ['title' => 'Dune'])->assertOk();

        $this->assertSame([['title' => 'New suggestion', 'body' => 'Mom suggested Dune']], $alerts);
    }

    public function test_blank_or_their_own_name_clears_the_nickname(): void
    {
        $this->friend->forceFill(['host_nickname' => 'Mom'])->save();
        $this->asHost()->post("/host/clients/{$this->friend->id}/rename", ['nickname' => ' '])->assertOk()->assertSee('>Laurie</p>', false);
        $this->assertNull($this->friend->fresh()->host_nickname);

        $this->friend->forceFill(['host_nickname' => 'Mom'])->save();
        $this->asHost()->post("/host/clients/{$this->friend->id}/rename", ['nickname' => 'Laurie'])->assertOk()
            ->assertDontSee('Calls themselves', false);
        $this->assertNull($this->friend->fresh()->host_nickname);
    }

    public function test_a_name_that_is_too_long_is_refused(): void
    {
        $this->asHost()->post("/host/clients/{$this->friend->id}/rename", ['nickname' => str_repeat('x', 41)])->assertOk()
            ->assertSee('Names can be at most 40 characters.', false)
            ->assertSee('value="'.str_repeat('x', 41).'"', false);

        $this->assertNull($this->friend->fresh()->host_nickname);
    }

    public function test_cancel_shows_the_row_again(): void
    {
        $this->asHost()->get("/host/clients/{$this->friend->id}/row.ham")->assertOk()
            ->assertSee('id="client-'.$this->friend->id.'"', false)->assertSee('Revoke access', false);
    }

    public function test_a_friend_cant_rename_anyone_and_the_host_cant_rename_another_librarys_friend(): void
    {
        $other = Client::factory()->create(['host_id' => Host::factory()->create()->id, 'display_name' => 'Sam']);

        $this->withSession(['client_id' => $this->friend->id])->post("/host/clients/{$this->friend->id}/rename", ['nickname' => 'Boss'])
            ->assertSee('Not authorized');
        $this->asHost()->post("/host/clients/{$other->id}/rename", ['nickname' => 'Stranger']);

        $this->assertNull($this->friend->fresh()->host_nickname);
        $this->assertNull($other->fresh()->host_nickname);
    }
}
