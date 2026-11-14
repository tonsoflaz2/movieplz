<?php

namespace Tests\Feature\Push;

use App\Models\Client;
use App\Models\Host;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * One phone, one person: registering a push token takes it off every other
 * client row (any library), so a phone that was a host and is now a friend
 * is notified only as the friend.
 */
class TokenMovesTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'a4913327aaaabbbbccccddddeeeeffff00001111222233334444555566667777';

    public function test_registering_takes_the_token_off_every_other_row_and_logs_ids_only(): void
    {
        $ownLibrary = Host::factory()->create();
        $oldHost = Client::factory()->host()->create([
            'host_id' => $ownLibrary->id, 'push_token' => self::TOKEN, 'push_platform' => 'ios',
            'push_environment' => 'development', 'disconnected_at' => now(),
        ]);
        $friendsLibrary = Host::factory()->create();
        $staleFriend = Client::factory()->create([
            'host_id' => $friendsLibrary->id, 'push_token' => self::TOKEN, 'push_platform' => 'ios',
        ]);
        $me = Client::factory()->create(['host_id' => $friendsLibrary->id]);
        $bystander = Client::factory()->create([
            'host_id' => $friendsLibrary->id, 'push_token' => 'someone-elses-token', 'push_platform' => 'android',
        ]);
        Log::spy();

        $this->withSession(['client_id' => $me->id])
            ->post('/api/devices?for='.$me->id, ['token' => self::TOKEN, 'platform' => 'ios'])
            ->assertOk();

        $this->assertSame(self::TOKEN, $me->fresh()->push_token);
        $this->assertNull($oldHost->fresh()->push_token);
        $this->assertNull($oldHost->fresh()->push_environment);
        $this->assertNull($staleFriend->fresh()->push_token);
        $this->assertSame('someone-elses-token', $bystander->fresh()->push_token);

        Log::shouldHaveReceived('info')->with('Push token moved', Mockery::on(function ($context) use ($oldHost, $staleFriend, $me) {
            $from = $context['from_clients'];
            sort($from);

            return $from === [$oldHost->id, $staleFriend->id]
                && $context['to_client'] === $me->id
                && ! str_contains(json_encode($context), 'a4913327');
        }))->once();
    }

    public function test_registering_again_on_the_same_row_moves_nothing(): void
    {
        $library = Host::factory()->create();
        $me = Client::factory()->create(['host_id' => $library->id, 'push_token' => self::TOKEN, 'push_platform' => 'ios']);
        Log::spy();

        $this->withSession(['client_id' => $me->id])
            ->post('/api/devices?for='.$me->id, ['token' => self::TOKEN, 'platform' => 'ios'])
            ->assertOk();

        $this->assertSame(self::TOKEN, $me->fresh()->push_token);
        Log::shouldNotHaveReceived('info', ['Push token moved', Mockery::any()]);
    }

    public function test_a_registration_without_a_session_moves_nothing(): void
    {
        $library = Host::factory()->create();
        $someone = Client::factory()->create(['host_id' => $library->id, 'push_token' => self::TOKEN, 'push_platform' => 'ios']);

        $this->post('/api/devices', ['token' => self::TOKEN, 'platform' => 'ios'])->assertOk();

        $this->assertSame(self::TOKEN, $someone->fresh()->push_token);
    }
}
