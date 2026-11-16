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
 * How the host answers a suggestion: "In progress", "Added" (into the
 * library while "Add to library?" is ticked) or "Declined", with an
 * optional reply the friend sees in their list and in the notification.
 */
class SuggestionReplyTest extends TestCase
{
    use RefreshDatabase;

    private Host $host;

    private Client $me;

    private Client $friend;

    /** @var list<array{title: string, body: string}> what Apple was asked to show */
    private array $alerts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = Host::factory()->create(['name' => "Christian's library"]);
        $this->me = Client::factory()->host()->create(['host_id' => $this->host->id, 'display_name' => 'Christian']);
        $this->friend = Client::factory()->create([
            'host_id' => $this->host->id, 'display_name' => 'Sam',
            'push_token' => str_repeat('a', 64), 'push_platform' => 'ios',
        ]);

        PushService::$apnsTransport = function ($token, $payload) {
            $this->alerts[] = json_decode($payload, true)['aps']['alert'];

            return [200, '', 'APNS-ID'];
        };
    }

    protected function tearDown(): void
    {
        PushService::$apnsTransport = null;
        parent::tearDown();
    }

    private function suggestion(array $attrs = []): MediaRequest
    {
        return MediaRequest::factory()->create($attrs + [
            'host_id' => $this->host->id, 'client_id' => $this->friend->id,
            'title' => 'Dune', 'year' => 2021, 'type' => 'film', 'status' => 'pending', 'notes' => null,
        ]);
    }

    private function asHost(): static
    {
        return $this->withSession(['client_id' => $this->me->id]);
    }

    public function test_a_new_suggestion_offers_the_three_answers_the_checkbox_and_a_reply(): void
    {
        $s = $this->suggestion();

        $this->asHost()->get('/host/requests.ham')->assertOk()
            ->assertSeeInOrder(['In progress', 'Added', 'Declined', 'Add to library?', 'name="reply"'], false)
            ->assertSee('action="/host/requests/'.$s->id.'/fulfill"', false)
            ->assertSee('<input type="hidden" name="add_sent" value="1"/>', false)
            ->assertSee('<checkbox name="add" checked="true" label="Add to library?"', false)
            ->assertDontSee('>Done<', false)
            ->assertDontSee('add=0', false);
    }

    public function test_in_progress_with_a_reply_tells_the_friend_and_shows_in_their_list(): void
    {
        $s = $this->suggestion();

        $this->asHost()->post("/host/requests/{$s->id}/up-next", ['reply' => 'Grabbing it this weekend'])
            ->assertOk()->assertSee('You replied: "Grabbing it this weekend"', false);

        $this->assertSame('Grabbing it this weekend', $s->refresh()->host_reply);
        $this->assertSame([['title' => 'Christian is working on Dune', 'body' => 'Grabbing it this weekend']], $this->alerts);
        $this->withSession(['client_id' => $this->friend->id])->get('/requests.ham')
            ->assertSee('In progress (1)', false)->assertSee('Reply: "Grabbing it this weekend"', false);
    }

    public function test_without_a_reply_the_notification_says_what_happened(): void
    {
        $s = $this->suggestion();

        $this->asHost()->post("/host/requests/{$s->id}/up-next")->assertOk();
        $this->asHost()->post("/host/requests/{$s->id}/fulfill")->assertOk();

        $this->assertNull($s->refresh()->host_reply);
        $this->assertSame([
            ['title' => 'Christian is working on Dune'],
            ['title' => 'Christian added Dune'],
        ], $this->alerts);
    }

    public function test_an_empty_reply_keeps_the_one_sent_before(): void
    {
        $s = $this->suggestion();

        $this->asHost()->post("/host/requests/{$s->id}/up-next", ['reply' => 'On it'])->assertOk();
        $this->asHost()->post("/host/requests/{$s->id}/fulfill", ['reply' => '   '])->assertOk()
            ->assertSee('You replied: "On it"', false);

        $this->assertSame('On it', $s->refresh()->host_reply);
    }

    /** @return array<string, array{0: array<string, string>}> */
    public static function untickedBoxes(): array
    {
        return [
            'sent as false' => [['add_sent' => '1', 'add' => 'false']],
            'sent as 0' => [['add_sent' => '1', 'add' => '0']],
            'left out' => [['add_sent' => '1']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('untickedBoxes')]
    public function test_added_with_the_box_unticked_leaves_the_library_alone(array $form): void
    {
        $s = $this->suggestion();

        $this->asHost()->post("/host/requests/{$s->id}/fulfill", $form + ['reply' => "Watched it at a friend's"])
            ->assertOk()->assertSee('not in your library', false);

        $s->refresh();
        $this->assertSame('fulfilled', $s->status);
        $this->assertNull($s->fulfilled_library_item_id);
        $this->assertSame('Added', $s->label());
        $this->assertFalse($s->wasAddedToLibrary());
        $this->assertSame(0, LibraryItem::where('host_id', $this->host->id)->count());
        $this->assertSame([['title' => 'Christian added Dune', 'body' => "Watched it at a friend's"]], $this->alerts);
        $this->withSession(['client_id' => $this->friend->id])->get('/requests.ham')
            ->assertSee('Added (1)', false)->assertDontSee('in the library', false);
    }

    public function test_added_without_a_reply_is_just_the_title_ticked_or_not(): void
    {
        $ticked = $this->suggestion();
        $unticked = $this->suggestion(['title' => 'Heat']);

        $this->asHost()->post("/host/requests/{$ticked->id}/fulfill", ['add_sent' => '1', 'add' => 'true'])->assertOk();
        $this->asHost()->post("/host/requests/{$unticked->id}/fulfill", ['add_sent' => '1', 'add' => 'false'])->assertOk();

        $this->assertSame([['title' => 'Christian added Dune'], ['title' => 'Christian added Heat']], $this->alerts);
    }

    /** @return array<string, array{0: array<string, string>}> */
    public static function tickedBoxes(): array
    {
        return [
            'sent as true' => [['add_sent' => '1', 'add' => 'true']],
            'sent as on' => [['add_sent' => '1', 'add' => 'on']],
            'sent as 1' => [['add_sent' => '1', 'add' => '1']],
            'no form at all' => [[]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tickedBoxes')]
    public function test_added_with_the_box_ticked_puts_it_in_the_library(array $form): void
    {
        $s = $this->suggestion();

        $this->asHost()->post("/host/requests/{$s->id}/fulfill", $form)->assertOk()
            ->assertSee('in your library', false)->assertDontSee('not in your library', false);

        $this->assertTrue($s->refresh()->wasAddedToLibrary());
        $this->assertSame('Added', $s->label());
        $this->assertSame([['title' => 'Christian added Dune']], $this->alerts);
    }

    public function test_declined_tells_the_friend_only_when_there_is_a_reply(): void
    {
        $quiet = $this->suggestion();
        $this->asHost()->post("/host/requests/{$quiet->id}/decline")->assertOk();
        $this->assertSame([], $this->alerts);

        $replied = $this->suggestion(['title' => 'Heat']);
        $this->asHost()->post("/host/requests/{$replied->id}/decline", ['reply' => 'Already on Plex, check again!'])
            ->assertOk()->assertSee('Declined', false)->assertSee('You replied: "Already on Plex, check again!"', false);

        $this->assertSame([['title' => 'Christian declined Heat', 'body' => 'Already on Plex, check again!']], $this->alerts);
        $this->assertSame('Declined', $replied->refresh()->label());
    }

    public function test_a_reply_that_is_too_long_changes_nothing(): void
    {
        $s = $this->suggestion();

        $this->asHost()->post("/host/requests/{$s->id}/fulfill", ['reply' => str_repeat('x', 501)])
            ->assertOk()->assertSee('Keep the reply to 500 characters or fewer.', false)
            ->assertSee('name="reply"', false);

        $s->refresh();
        $this->assertSame('pending', $s->status);
        $this->assertNull($s->host_reply);
        $this->assertSame(0, LibraryItem::count());
        $this->assertSame([], $this->alerts);
    }

    public function test_another_hosts_suggestion_cant_be_answered(): void
    {
        $other = Host::factory()->create();
        $theirs = MediaRequest::factory()->create(['host_id' => $other->id, 'client_id' => Client::factory()->create(['host_id' => $other->id])->id]);

        $this->asHost()->post("/host/requests/{$theirs->id}/decline", ['reply' => 'nope'])->assertSee('icon name="error', false);

        $theirs->refresh();
        $this->assertSame('pending', $theirs->status);
        $this->assertNull($theirs->host_reply);
    }
}
