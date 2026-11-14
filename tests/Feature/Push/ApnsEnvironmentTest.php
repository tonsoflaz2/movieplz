<?php

namespace Tests\Feature\Push;

use App\Models\Client;
use App\Models\Host;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An App Store or TestFlight build's token works only on Apple's production
 * gateway, a development build's only on the sandbox: each token's
 * environment is stored with its client, sent by the device or learnt from
 * Apple's "BadDeviceToken". Apple is faked by PushService::$apnsTransport.
 */
class ApnsEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    private Client $friend;

    /** @var array<int, string> the environments tried, in order */
    private array $tried = [];

    protected function setUp(): void
    {
        parent::setUp();
        $host = Host::factory()->create();
        $this->friend = Client::factory()->create(['host_id' => $host->id]);
    }

    protected function tearDown(): void
    {
        PushService::$apnsTransport = null;
        parent::tearDown();
    }

    /** Apple, where the token is valid only in $valid ('production', 'development' or null). */
    private function apple(?string $valid): void
    {
        PushService::$apnsTransport = function ($token, $payload, $environment) use ($valid) {
            $this->tried[] = $environment;

            return $environment === $valid ? [200, ''] : [400, '{"reason":"BadDeviceToken"}'];
        };
    }

    private function givenToken(?string $environment): void
    {
        $this->friend->forceFill(['push_token' => 'apns-token', 'push_platform' => 'ios', 'push_environment' => $environment])->save();
    }

    public function test_an_unknown_token_tries_production_first_and_remembers_it(): void
    {
        $this->givenToken(null);
        $this->apple('production');

        PushService::send('apns-token', 'ios', 'A', 'a');

        $this->assertSame(['production'], $this->tried);
        $this->assertSame('production', $this->friend->fresh()->push_environment);
    }

    public function test_a_development_token_is_found_on_the_sandbox_and_remembered(): void
    {
        $this->givenToken(null);
        $this->apple('development');

        PushService::send('apns-token', 'ios', 'A', 'a');
        $this->assertSame(['production', 'development'], $this->tried);
        $this->assertSame('development', $this->friend->fresh()->push_environment);

        // The next push goes straight to the sandbox.
        $this->tried = [];
        PushService::send('apns-token', 'ios', 'B', 'b');
        $this->assertSame(['development'], $this->tried);
    }

    public function test_a_stored_environment_is_tried_first_and_corrected_if_wrong(): void
    {
        $this->givenToken('development');
        $this->apple('production');

        PushService::send('apns-token', 'ios', 'A', 'a');

        $this->assertSame(['development', 'production'], $this->tried);
        $this->assertSame('production', $this->friend->fresh()->push_environment);
    }

    public function test_a_token_neither_gateway_knows_is_forgotten(): void
    {
        $this->givenToken(null);
        $this->apple(null);

        PushService::send('apns-token', 'ios', 'A', 'a');

        $this->assertSame(['production', 'development'], $this->tried);
        $this->assertNull($this->friend->fresh()->push_token);
    }

    public function test_another_rejection_is_not_retried_on_the_other_gateway(): void
    {
        $this->givenToken(null);
        PushService::$apnsTransport = function ($token, $payload, $environment) {
            $this->tried[] = $environment;

            return [403, '{"reason":"InvalidProviderToken"}'];
        };

        PushService::send('apns-token', 'ios', 'A', 'a');

        $this->assertSame(['production'], $this->tried);
        $this->assertSame('apns-token', $this->friend->fresh()->push_token);
    }

    public function test_a_device_can_say_which_environment_its_token_is(): void
    {
        $this->withSession(['client_id' => $this->friend->id])
            ->post('/api/devices', ['token' => 'apns-dev', 'platform' => 'ios', 'apns_environment' => 'development'])->assertOk();
        $this->assertSame('development', $this->friend->fresh()->push_environment);

        // Re-registering the same token without saying keeps what's known.
        $this->withSession(['client_id' => $this->friend->id])
            ->post('/api/devices', ['token' => 'apns-dev', 'platform' => 'ios'])->assertOk();
        $this->assertSame('development', $this->friend->fresh()->push_environment);

        // A new token starts unknown.
        $this->withSession(['client_id' => $this->friend->id])
            ->post('/api/devices', ['token' => 'apns-new', 'platform' => 'ios'])->assertOk();
        $this->assertNull($this->friend->fresh()->push_environment);
    }
}
