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

    public function test_in_progress_keeps_it_pending_and_up_next(): void
    {
        $s = $this->suggestion();

        $this->withSession(['client_id' => $this->me->id])
            ->post("/host/requests/{$s->id}/up-next")
            ->assertOk()
            ->assertSee('In progress since', false)
            ->assertSee('/fulfill"', false)
            ->assertDontSee('/up-next"', false);

        $s->refresh();
        $this->assertSame('pending', $s->status);
        $this->assertNotNull($s->up_next_at);
        $this->assertSame('In progress', $s->label());
    }

    public function test_added_puts_the_title_in_the_hosts_library(): void
    {
        $s = $this->suggestion(['up_next_at' => now()]);

        $this->withSession(['client_id' => $this->me->id])->post("/host/requests/{$s->id}/fulfill")->assertOk();

        $item = LibraryItem::where('host_id', $this->host->id)->where('title', 'Paddington in Peru')->sole();
        $this->assertSame(2024, $item->year);
        $this->assertSame($item->id, $s->refresh()->fulfilled_library_item_id);
        $this->assertSame('Added', $s->label());
    }

    public function test_added_reuses_a_title_already_in_the_library(): void
    {
        LibraryItem::factory()->create(['host_id' => $this->host->id, 'title' => 'Paddington in Peru', 'year' => 2024, 'type' => 'film']);
        $s = $this->suggestion();

        $this->withSession(['client_id' => $this->me->id])->post("/host/requests/{$s->id}/fulfill")->assertOk();

        $this->assertSame(1, LibraryItem::where('host_id', $this->host->id)->count());
    }

    public function test_declined_shows_as_declined(): void
    {
        $s = $this->suggestion();

        $this->withSession(['client_id' => $this->me->id])
            ->post("/host/requests/{$s->id}/decline")
            ->assertOk()
            ->assertSee('Declined', false);

        $this->assertSame('Declined', $s->refresh()->label());
    }

    public function test_in_progress_is_not_counted_as_new(): void
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

    public function test_friend_sees_in_progress_and_added(): void
    {
        $this->suggestion(['up_next_at' => now()]);
        $item = LibraryItem::factory()->create(['host_id' => $this->host->id, 'title' => 'Heat']);
        $this->suggestion(['title' => 'Heat', 'status' => 'fulfilled', 'fulfilled_at' => now(), 'fulfilled_library_item_id' => $item->id]);

        $this->withSession(['client_id' => $this->friend->id])
            ->get('/requests.ham')
            ->assertSee('In progress (1)', false)
            ->assertSee('Added (1)', false)
            ->assertSee('in the library', false);
    }

    public function test_friend_is_told_when_its_in_progress_and_when_its_added(): void
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
