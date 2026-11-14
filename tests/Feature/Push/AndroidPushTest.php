<?php

namespace Tests\Feature\Push;

use App\Models\Client;
use App\Models\Host;
use App\Models\MediaRequest;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Android notifications through Firebase Cloud Messaging (HTTP v1), with
 * Google's token and send endpoints faked, and iOS still through APNs.
 */
class AndroidPushTest extends TestCase
{
    use RefreshDatabase;

    private const FCM_URL = 'https://fcm.googleapis.com/v1/projects/movieplz-test/messages:send';

    private Host $host;

    private Client $me;

    private Client $friend;

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();
        // A throwaway service-account key, made for this test.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->publicKey = openssl_pkey_get_details($key)['key'];
        config(['push.fcm.credentials' => base64_encode(json_encode([
            'type' => 'service_account',
            'project_id' => 'movieplz-test',
            'client_email' => 'push@movieplz-test.iam.gserviceaccount.com',
            'private_key' => $pem,
        ]))]);
        Cache::flush();

        $this->host = Host::factory()->create(['name' => "Christian's library"]);
        $this->me = Client::factory()->host()->create(['host_id' => $this->host->id, 'display_name' => 'Christian']);
        $this->friend = Client::factory()->create(['host_id' => $this->host->id, 'display_name' => 'Sam']);
    }

    protected function tearDown(): void
    {
        PushService::$apnsTransport = null;
        parent::tearDown();
    }

    private function fakeGoogle(?array $send = null): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response($send ?? ['name' => 'projects/movieplz-test/messages/1']),
        ]);
    }

    public function test_an_android_device_gets_a_data_only_message_from_fcm(): void
    {
        $this->fakeGoogle();

        PushService::send('fcm-token-abc', 'android', 'New suggestion', 'Sam suggested Heat', ['route' => '/host/requests.ham', 'n' => 3]);
        // Firebase's name for the message goes in the INFO log line.
        $this->assertSame('projects/movieplz-test/messages/1', \App\Services\FcmSender::$lastMessageId);

        Http::assertSent(function (Request $r) {
            if ($r->url() !== self::FCM_URL) {
                return false;
            }
            $m = $r['message'];

            return $r->hasHeader('Authorization', 'Bearer ya29.test')
                && $m['token'] === 'fcm-token-abc'
                && ! isset($m['notification'])
                && $m['data'] === ['route' => '/host/requests.ham', 'n' => '3', 'title' => 'New suggestion', 'body' => 'Sam suggested Heat']
                && $m['android'] === ['priority' => 'high'];
        });
    }

    public function test_the_service_account_jwt_is_signed_and_the_access_token_is_reused(): void
    {
        $this->fakeGoogle();

        PushService::send('t1', 'android', 'A', 'a');
        PushService::send('t2', 'android', 'B', 'b');

        $tokenCalls = Http::recorded(fn (Request $r) => str_contains($r->url(), 'oauth2.googleapis.com'));
        $this->assertCount(1, $tokenCalls, 'one OAuth exchange for both sends');
        [$request] = $tokenCalls->first();
        $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $request['grant_type']);
        [$header, $claims, $signature] = explode('.', $request['assertion']);
        $decode = fn ($s) => base64_decode(strtr($s, '-_', '+/'));
        $this->assertSame('RS256', json_decode($decode($header), true)['alg']);
        $c = json_decode($decode($claims), true);
        $this->assertSame('push@movieplz-test.iam.gserviceaccount.com', $c['iss']);
        $this->assertSame('https://www.googleapis.com/auth/firebase.messaging', $c['scope']);
        $this->assertSame(1, openssl_verify("$header.$claims", $decode($signature), $this->publicKey, OPENSSL_ALGO_SHA256));
        Http::assertSentCount(3);
    }

    public function test_a_token_fcm_says_is_unregistered_is_forgotten(): void
    {
        $this->friend->forceFill(['push_token' => 'old-token', 'push_platform' => 'android'])->save();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'fcm.googleapis.com/*' => Http::response(['error' => [
                'code' => 404, 'status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.',
                'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']],
            ]], 404),
        ]);

        PushService::send('old-token', 'android', 'A', 'a');

        $this->assertNull($this->friend->fresh()->push_token);
    }

    public function test_other_fcm_failures_keep_the_token(): void
    {
        $this->friend->forceFill(['push_token' => 'good-token', 'push_platform' => 'android'])->save();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE']], 503),
        ]);

        PushService::send('good-token', 'android', 'A', 'a');

        $this->assertSame('good-token', $this->friend->fresh()->push_token);
    }

    public function test_without_credentials_android_pushes_are_skipped_with_a_warning(): void
    {
        config(['push.fcm.credentials' => null]);
        Http::fake();
        Log::spy();

        PushService::send('fcm-token', 'android', 'A', 'a');

        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'FCM push skipped'))->once();
    }

    public function test_ios_still_goes_to_apns_and_a_410_forgets_the_token(): void
    {
        $this->friend->forceFill(['push_token' => 'apns-token', 'push_platform' => 'ios'])->save();
        Http::fake();
        $sent = [];
        PushService::$apnsTransport = function ($token, $payload) use (&$sent) {
            $sent[] = [$token, json_decode($payload, true)];

            return [410, '{"reason":"Unregistered"}'];
        };

        PushService::send('apns-token', 'ios', 'Sam saw it!', 'Heat is on the list', ['route' => '/requests.ham']);

        Http::assertNothingSent();
        $this->assertSame('apns-token', $sent[0][0]);
        $this->assertSame(['title' => 'Sam saw it!', 'body' => 'Heat is on the list'], $sent[0][1]['aps']['alert']);
        $this->assertNull($this->friend->fresh()->push_token);
    }

    public function test_the_suggestion_flow_notifies_an_android_host_and_an_android_friend(): void
    {
        $this->me->forceFill(['push_token' => 'host-fcm', 'push_platform' => 'android'])->save();
        $this->friend->forceFill(['push_token' => 'friend-fcm', 'push_platform' => 'android'])->save();
        $this->fakeGoogle();

        $this->withSession(['client_id' => $this->friend->id])
            ->post('/requests', ['title' => 'Heat', 'year' => '1995', 'type' => 'film', 'notes' => ''])
            ->assertOk();
        $s = MediaRequest::where('title', 'Heat')->sole();
        $this->withSession(['client_id' => $this->me->id])->post("/host/requests/{$s->id}/fulfill")->assertOk();

        $sends = Http::recorded(fn (Request $r) => $r->url() === self::FCM_URL)->map(fn ($pair) => $pair[0]['message'])->values();
        $this->assertSame(['host-fcm', 'friend-fcm'], $sends->pluck('token')->all());
        $this->assertSame('New suggestion', $sends[0]['data']['title']);
        $this->assertSame('/host/requests.ham', $sends[0]['data']['route']);
        $this->assertSame('/requests.ham', $sends[1]['data']['route']);
    }

    public function test_a_device_registers_as_android_or_ios(): void
    {
        $this->withSession(['client_id' => $this->friend->id])
            ->post('/api/devices', ['token' => 'fcm-xyz', 'platform' => 'android'])->assertOk();
        $this->assertSame(['fcm-xyz', 'android'], [$this->friend->fresh()->push_token, $this->friend->fresh()->push_platform]);

        $this->withSession(['client_id' => $this->friend->id])
            ->post('/api/devices', ['token' => 'apns-xyz', 'platform' => 'windows'])->assertOk();
        $this->assertSame('ios', $this->friend->fresh()->push_platform);
    }
}
