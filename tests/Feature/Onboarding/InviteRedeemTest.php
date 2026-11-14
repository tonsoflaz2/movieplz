<?php

namespace Tests\Feature\Onboarding;

use App\Models\Host;
use App\Models\Invite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteRedeemTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_code_returns_name_form(): void
    {
        $host = Host::factory()->create();
        $invite = Invite::factory()->create([
            'host_id' => $host->id,
            'code' => '54321',
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->post('/onboarding/invite', ['code' => '54321']);

        $response->assertOk();
        $response->assertSee('One more thing', false);
        $response->assertSee('display_name', false);
        $this->assertSame($invite->id, session('pending_invite_id'));
    }

    public function test_valid_code_with_spaces_still_works(): void
    {
        $host = Host::factory()->create();
        Invite::factory()->create([
            'host_id' => $host->id,
            'code' => '12345',
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->post('/onboarding/invite', ['code' => ' 12345 ']);

        $response->assertOk();
        $response->assertSee('One more thing', false);
    }

    public function test_unknown_code_returns_form_with_error(): void
    {
        $response = $this->post('/onboarding/invite', ['code' => '99999']);

        $response->assertOk();
        $response->assertSee('Code not recognized or expired', false);
        $response->assertSee('Enter your code', false);
        $this->assertNull(session('pending_invite_id'));
    }

    public function test_expired_code_is_rejected(): void
    {
        $host = Host::factory()->create();
        Invite::factory()->create([
            'host_id' => $host->id,
            'code' => '11111',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->post('/onboarding/invite', ['code' => '11111']);

        $response->assertOk();
        $response->assertSee('Code not recognized or expired', false);
    }

    public function test_already_redeemed_code_is_rejected(): void
    {
        $host = Host::factory()->create();
        Invite::factory()->create([
            'host_id' => $host->id,
            'code' => '22222',
            'expires_at' => now()->addMinutes(5),
            'redeemed_at' => now(),
        ]);

        $response = $this->post('/onboarding/invite', ['code' => '22222']);

        $response->assertOk();
        $response->assertSee('Code not recognized or expired', false);
    }

    public function test_bad_format_returns_form_with_error(): void
    {
        $response = $this->post('/onboarding/invite', ['code' => 'abcde']);

        $response->assertOk();
        $response->assertSee('5 digits', false);
    }

    public function test_empty_code_returns_form_with_error(): void
    {
        $response = $this->post('/onboarding/invite', ['code' => '']);

        $response->assertOk();
        $response->assertSee('Enter your 5-digit code.', false);
    }

    public function test_code_with_leading_zero_posted_as_number_still_works(): void
    {
        $host = Host::factory()->create();
        $invite = Invite::factory()->create([
            'host_id' => $host->id,
            'code' => '03921',
            'expires_at' => now()->addMinutes(5),
        ]);

        // The HAM client posts the digits as a number: 03921 arrives as 3921.
        $response = $this->post('/onboarding/invite', ['code' => '3921']);

        $response->assertOk();
        $response->assertSee('One more thing', false);
        $this->assertSame($invite->id, session('pending_invite_id'));
    }
}
