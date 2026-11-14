<?php

namespace Tests\Feature\Requests;

use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SuggestionFlowTest extends TestCase
{
    use RefreshDatabase;

    private Host $host;
    private Client $me;
    private Client $friend;

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = Host::factory()->create(['name' => "Christian's library"]);
        $this->me = Client::factory()->host()->create(['host_id' => $this->host->id, 'display_name' => 'Christian']);
        $this->friend = Client::factory()->create(['host_id' => $this->host->id, 'display_name' => 'Sam']);
    }

    private function suggestion(array $attrs = []): MediaRequest
    {
        return MediaRequest::factory()->create($attrs + [
            'host_id' => $this->host->id, 'client_id' => $this->friend->id,
            'title' => 'Paddington in Peru', 'year' => 2024, 'type' => 'film', 'status' => 'pending',
        ]);
    }

    public function test_ill_watch_it_puts_it_up_next(): void
    {
        $s = $this->suggestion();

        $this->withSession(['client_id' => $this->me->id])
            ->post("/host/requests/{$s->id}/up-next")
            ->assertOk()
            ->assertSee('Up next since', false)
            ->assertSee('Saw it!', false)
            ->assertDontSee("I'll watch it", false);

        $s->refresh();
        $this->assertSame('pending', $s->status);
        $this->assertNotNull($s->up_next_at);
        $this->assertSame('Up next', $s->label());
    }

    public function test_saw_it_puts_the_title_on_the_hosts_list(): void
    {
        $s = $this->suggestion(['up_next_at' => now()]);

        $this->withSession(['client_id' => $this->me->id])->post("/host/requests/{$s->id}/fulfill")->assertOk();

        $item = LibraryItem::where('host_id', $this->host->id)->where('title', 'Paddington in Peru')->sole();
        $this->assertSame(2024, $item->year);
        $this->assertSame($item->id, $s->refresh()->fulfilled_library_item_id);
        $this->assertSame('Seen', $s->label());
    }

    public function test_saw_it_reuses_a_title_already_on_the_list(): void
    {
        LibraryItem::factory()->create(['host_id' => $this->host->id, 'title' => 'Paddington in Peru', 'year' => 2024, 'type' => 'film']);
        $s = $this->suggestion();

        $this->withSession(['client_id' => $this->me->id])->post("/host/requests/{$s->id}/fulfill")->assertOk();

        $this->assertSame(1, LibraryItem::where('host_id', $this->host->id)->count());
    }

    public function test_not_for_me_shows_as_passed(): void
    {
        $s = $this->suggestion();

        $this->withSession(['client_id' => $this->me->id])
            ->post("/host/requests/{$s->id}/decline")
            ->assertOk()
            ->assertSee('Passed', false);

        $this->assertSame('Passed', $s->refresh()->label());
    }

    public function test_up_next_is_not_counted_as_new(): void
    {
        $this->suggestion();
        $this->suggestion(['title' => 'Heat', 'up_next_at' => now()]);

        $this->withSession(['client_id' => $this->me->id])
            ->get('/host/unread.ham')
            ->assertSee('1 new suggestion', false);

        $this->withSession(['client_id' => $this->me->id])
            ->get('/host/requests.ham?status=up_next')
            ->assertSee('Heat', false)
            ->assertDontSee('Paddington in Peru', false);
    }

    public function test_friend_sees_up_next_and_seen(): void
    {
        $this->suggestion(['up_next_at' => now()]);
        $this->suggestion(['title' => 'Heat', 'status' => 'fulfilled', 'fulfilled_at' => now()]);

        $this->withSession(['client_id' => $this->friend->id])
            ->get('/requests.ham')
            ->assertSee('Up next (1)', false)
            ->assertSee('Seen (1)', false);
    }

    public function test_friend_is_told_when_the_host_is_up_for_it_and_when_they_saw_it(): void
    {
        $this->friend->forceFill(['push_token' => str_repeat('0', 64), 'push_platform' => 'ios'])->save();
        config(['apns.key_path' => 'nonexistent.p8']); // PushService logs and gives up; we only check it was asked
        Log::spy();
        $s = $this->suggestion();

        $this->withSession(['client_id' => $this->me->id])->post("/host/requests/{$s->id}/up-next");
        $this->withSession(['client_id' => $this->me->id])->post("/host/requests/{$s->id}/fulfill");

        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'APNs push failed'))->twice();
    }
}
