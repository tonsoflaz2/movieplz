<?php

namespace Tests\Feature\Push;

use App\Models\Client;
use App\Models\Host;
use App\Models\MediaRequest;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Who gets told when the host answers a suggestion, and the one INFO log
 * line each notification leaves: sent (with Apple's apns-id), rejected, or
 * skipped and why. Never the token.
 *
 * The shape of the 2026-11-13 report: the host is on Android; the friend
 * who suggested is on an iPhone whose token is also on the person's own
 * (disconnected) host row from earlier.
 */
class PushLogTest extends TestCase
{
    use RefreshDatabase;

    private const IOS_TOKEN = 'a4913327aaaabbbbccccddddeeeeffff00001111222233334444555566667777';

    private Client $host;

    private Client $friend;

    private Client $oldHostRow;

    /** @var list<array{token: string, environment: string}> */
    private array $apple = [];

    protected function setUp(): void
    {
        parent::setUp();
        $library = Host::factory()->create(['name' => "Grace's library"]);
        $this->host = Client::factory()->host()->create([
            'host_id' => $library->id, 'display_name' => 'Grace',
            'push_token' => 'fcm-grace', 'push_platform' => 'android',
        ]);
        $this->friend = Client::factory()->create([
            'host_id' => $library->id, 'display_name' => 'Lazarus',
            'push_token' => self::IOS_TOKEN, 'push_platform' => 'ios',
        ]);
        // The same iPhone, earlier signed in as the host of its own library.
        $own = Host::factory()->create();
        $this->oldHostRow = Client::factory()->host()->create([
            'host_id' => $own->id, 'push_token' => self::IOS_TOKEN, 'push_platform' => 'ios',
            'disconnected_at' => now(),
        ]);

        PushService::$apnsTransport = function ($token, $payload, $environment) {
            $this->apple[] = ['token' => $token, 'environment' => $environment];

            return [200, '', 'APNS-ID-1'];
        };
        Log::spy();
    }

    protected function tearDown(): void
    {
        PushService::$apnsTransport = null;
        parent::tearDown();
    }

    private function suggestion(): MediaRequest
    {
        return MediaRequest::factory()->create([
            'host_id' => $this->host->host_id, 'client_id' => $this->friend->id,
            'title' => 'Heat', 'status' => MediaRequest::STATUS_PENDING,
        ]);
    }

    private function assertLogged(string $message, array $expected): void
    {
        Log::shouldHaveReceived('info')->with($message, Mockery::on(function ($context) use ($expected) {
            foreach ($expected as $k => $v) {
                if (($context[$k] ?? null) !== $v) {
                    return false;
                }
            }
            // Never the token, whole or in part.
            return ! str_contains(json_encode($context), 'a4913327');
        }))->once();
    }

    public function test_saw_it_from_an_android_host_reaches_the_friend_who_suggested_on_their_iphone(): void
    {
        $suggestion = $this->suggestion();

        $this->withSession(['client_id' => $this->host->id])
            ->post("/host/requests/{$suggestion->id}/fulfill")->assertOk();

        $this->assertSame([['token' => self::IOS_TOKEN, 'environment' => 'production']], $this->apple);
        $this->assertLogged('Push', [
            'client' => $this->friend->id, 'event' => 'saw-it', 'platform' => 'ios',
            'result' => 'sent', 'environment' => 'production', 'status' => 200, 'apns_id' => 'APNS-ID-1',
        ]);
    }

    public function test_up_for_it_reaches_the_friend_too_and_saw_it_after_it_still_does(): void
    {
        $suggestion = $this->suggestion();

        $this->withSession(['client_id' => $this->host->id])->post("/host/requests/{$suggestion->id}/up-next")->assertOk();
        $this->withSession(['client_id' => $this->host->id])->post("/host/requests/{$suggestion->id}/fulfill")->assertOk();

        $this->assertCount(2, $this->apple);
        $this->assertLogged('Push', ['client' => $this->friend->id, 'event' => 'up-for-it', 'result' => 'sent']);
        $this->assertLogged('Push', ['client' => $this->friend->id, 'event' => 'saw-it', 'result' => 'sent']);
    }

    public function test_a_suggestion_already_marked_saw_it_doesnt_notify_again(): void
    {
        $suggestion = $this->suggestion();
        $this->withSession(['client_id' => $this->host->id])->post("/host/requests/{$suggestion->id}/fulfill");
        $this->withSession(['client_id' => $this->host->id])->post("/host/requests/{$suggestion->id}/fulfill");

        $this->assertCount(1, $this->apple);
    }

    public function test_a_skipped_notification_says_why(): void
    {
        $suggestion = $this->suggestion();
        $this->friend->forceFill(['disconnected_at' => now()])->save();
        $this->withSession(['client_id' => $this->host->id])->post("/host/requests/{$suggestion->id}/up-next");

        $this->friend->forceFill(['disconnected_at' => null, 'push_token' => null])->save();
        $this->withSession(['client_id' => $this->host->id])->post("/host/requests/{$suggestion->id}/fulfill");

        $this->assertSame([], $this->apple);
        $this->assertLogged('Push skipped', ['client' => $this->friend->id, 'event' => 'up-for-it', 'reason' => 'disconnected']);
        $this->assertLogged('Push skipped', ['client' => $this->friend->id, 'event' => 'saw-it', 'reason' => 'no token']);
    }

    public function test_apples_rejection_is_logged_with_its_status(): void
    {
        PushService::$apnsTransport = fn () => [403, '{"reason":"InvalidProviderToken"}', 'APNS-ID-2'];
        $suggestion = $this->suggestion();

        $this->withSession(['client_id' => $this->host->id])->post("/host/requests/{$suggestion->id}/fulfill");

        $this->assertLogged('Push', [
            'client' => $this->friend->id, 'event' => 'saw-it', 'result' => 'failed', 'status' => 403, 'apns_id' => 'APNS-ID-2',
        ]);
    }

    public function test_a_new_suggestion_tells_the_android_host_and_logs_the_outcome(): void
    {
        config(['push.fcm.credentials' => null]); // Firebase not set up: a logged failure, nothing sent

        $this->withSession(['client_id' => $this->friend->id])
            ->post('/requests', ['title' => 'Heat', 'year' => '1995', 'type' => 'film', 'notes' => ''])
            ->assertOk();

        $this->assertLogged('Push', [
            'client' => $this->host->id, 'event' => 'new-suggestion', 'platform' => 'android', 'result' => 'failed',
        ]);
    }
}
