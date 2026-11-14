<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use App\Support\ReconnectCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Disconnecting a phone keeps everything; the reconnect code brings the
 * person back, on that phone or another.
 */
class DisconnectReconnectTest extends TestCase
{
    use RefreshDatabase;

    private Host $host;

    private Client $me;

    private Client $friend;

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = Host::factory()->create(['name' => 'Movie Night']);
        $this->me = Client::factory()->host()->create(['host_id' => $this->host->id, 'display_name' => 'Christian']);
        $this->friend = Client::factory()->create(['host_id' => $this->host->id, 'display_name' => 'Sam']);
    }

    public function test_codes_are_twelve_unambiguous_characters_and_forgive_case_spaces_and_dashes(): void
    {
        $code = ReconnectCode::generate();

        $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/', $code);
        $this->assertTrue(ReconnectCode::looksValid(strtolower(str_replace('-', ' ', $code))));
        $this->assertSame(ReconnectCode::hash($code), ReconnectCode::hash(strtolower($code)));
        $this->assertFalse(ReconnectCode::looksValid('ABCD-EFGH-IJK0'), 'I and 0 are not in the alphabet');
        $this->assertFalse(ReconnectCode::looksValid('12345'));
    }

    public function test_both_home_screens_offer_disconnect_and_the_welcome_screen_offers_reconnect(): void
    {
        $this->withSession(['client_id' => $this->me->id])->get('/app.ham')
            ->assertSee('Disconnect this phone')->assertSee('Your library, friends and suggestions are kept')
            ->assertSee('Delete my library');
        $this->withSession(['client_id' => $this->friend->id])->get('/app.ham')
            ->assertSee('Disconnect this phone')->assertSee('Your suggestions are kept')
            ->assertSee('Leave this library');
        $this->flushSession();
        $this->get('/app.ham')->assertSee('action="navigate:/onboarding/reconnect.ham"', false);
    }

    public function test_the_disconnect_step_shows_the_same_code_each_time_and_stores_it_encrypted(): void
    {
        $first = $this->withSession(['client_id' => $this->me->id])->get('/account/disconnect.ham')->assertOk();
        $code = $this->me->fresh()->reconnect_code;

        $this->assertNotNull($code);
        $first->assertSee($code)->assertSee('clipboard:copy:'.$code, false);
        $this->withSession(['client_id' => $this->me->id])->get('/account/disconnect.ham')->assertSee($code);
        $this->assertSame($code, $this->me->fresh()->reconnect_code);

        $raw = DB::table('clients')->where('id', $this->me->id)->first();
        $this->assertStringNotContainsString(ReconnectCode::normalize($code), (string) $raw->reconnect_code);
        $this->assertStringNotContainsString($code, (string) $raw->reconnect_code);
        $this->assertSame(ReconnectCode::hash($code), $raw->reconnect_code_hash);
        $this->assertArrayNotHasKey('reconnect_code', $this->me->fresh()->toArray());
    }

    public function test_a_host_disconnects_without_losing_anything(): void
    {
        LibraryItem::factory()->create(['host_id' => $this->host->id]);
        MediaRequest::factory()->create(['host_id' => $this->host->id, 'client_id' => $this->friend->id]);

        $response = $this->withSession(['client_id' => $this->me->id])
            ->post('/account/disconnect')
            ->assertOk()
            ->assertSee('This phone is disconnected')
            ->assertSee('id="disconnect-actions"', false)
            ->assertSee('app:/app.ham', false)
            ->assertSessionMissing('client_id');

        $code = $this->me->fresh()->reconnect_code;
        $response->assertSee($code);
        $this->assertNotNull($this->me->fresh()->disconnected_at);
        $this->assertModelExists($this->host);
        $this->assertSame(1, LibraryItem::count());
        $this->assertSame(1, MediaRequest::count());
        $this->assertSame(2, Client::count());
    }

    public function test_a_host_reconnects_with_their_code_typed_any_old_way(): void
    {
        $code = $this->me->reconnectCode();
        $this->me->forceFill(['disconnected_at' => now()])->save();

        $this->post('/onboarding/reconnect', ['code' => ' '.strtolower(str_replace('-', ' ', $code)).' '])
            ->assertOk()
            ->assertSee('Welcome back, Christian')
            ->assertSee('Movie Night')
            ->assertSee('app:/app.ham', false)
            ->assertSessionHas('client_id', $this->me->id);

        $this->assertNull($this->me->fresh()->disconnected_at);
        $this->get('/app.ham')->assertSee('bottom-nav-item label="Dashboard"', false);
    }

    public function test_a_friend_disconnects_keeping_their_suggestions_and_comes_back_to_them(): void
    {
        MediaRequest::factory()->create(['host_id' => $this->host->id, 'client_id' => $this->friend->id, 'title' => 'Heat']);

        $this->withSession(['client_id' => $this->friend->id])
            ->post('/account/disconnect')
            ->assertOk()
            ->assertSee('Your suggestions are kept');
        $code = $this->friend->fresh()->reconnect_code;

        $this->assertSame(1, MediaRequest::where('client_id', $this->friend->id)->count());
        $this->withSession(['client_id' => $this->me->id])->get('/host/clients.ham')
            ->assertSee('Sam')->assertSee('can reconnect');
        $this->withSession(['client_id' => $this->me->id])->get('/app.ham')->assertSee('0 friends connected');

        $this->flushSession();
        $this->post('/onboarding/reconnect', ['code' => $code])
            ->assertSee('Welcome back, Sam')
            ->assertSessionHas('client_id', $this->friend->id);
        $this->get('/requests.ham')->assertSee('Heat');
    }

    public function test_nobody_is_pushed_while_disconnected(): void
    {
        $this->friend->forceFill(['push_token' => 'tok', 'push_platform' => 'android'])->save();
        $this->assertTrue($this->friend->fresh()->canBePushed());

        $this->friend->forceFill(['disconnected_at' => now()])->save();
        $this->assertFalse($this->friend->fresh()->canBePushed());

        $this->friend->forceFill(['disconnected_at' => null, 'revoked_at' => now()])->save();
        $this->assertFalse($this->friend->fresh()->canBePushed());
    }

    public function test_another_phone_still_signed_in_as_the_person_counts_as_connected(): void
    {
        $this->me->forceFill(['disconnected_at' => now()])->save();

        $this->withSession(['client_id' => $this->me->id])->get('/app.ham')->assertOk();

        $this->assertNull($this->me->fresh()->disconnected_at);
    }

    public function test_wrong_unknown_and_revoked_codes_dont_get_in(): void
    {
        $friendCode = $this->friend->reconnectCode();
        $this->friend->forceFill(['revoked_at' => now(), 'disconnected_at' => now()])->save();

        foreach (['', 'nope', '12345', 'ABCD-EFGH-JKMN', $friendCode] as $tried) {
            $this->flushSession();
            $this->post('/onboarding/reconnect', ['code' => $tried])
                ->assertOk()
                ->assertSee('id="reconnect-form"', false)
                ->assertDontSee('Welcome back')
                ->assertSessionMissing('client_id');
        }
    }

    public function test_a_deleted_librarys_codes_go_with_it(): void
    {
        $code = $this->me->reconnectCode();
        $this->withSession(['client_id' => $this->me->id])->post('/host/delete-library')->assertOk();

        $this->flushSession();
        $this->post('/onboarding/reconnect', ['code' => $code])->assertDontSee('Welcome back');
    }

    public function test_reconnecting_is_rate_limited_and_the_form_says_so(): void
    {
        $code = $this->me->reconnectCode();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/onboarding/reconnect', ['code' => 'ABCD-EFGH-JKMN'])->assertSee("isn't recognized");
        }

        // Even the right code waits now.
        $this->post('/onboarding/reconnect', ['code' => $code])
            ->assertOk()
            ->assertSee('id="reconnect-form"', false)
            ->assertSee('Too many tries')
            ->assertSessionMissing('client_id');
    }
}
