<?php

namespace Tests\Feature\Onboarding;

use App\Models\Client;
use App\Models\Host;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_review_code_joins_the_demo_library_every_time(): void
    {
        $demo = Host::factory()->create(['name' => 'MoviePlz Demo']);
        config(['movieplz.review_code' => '24680', 'movieplz.review_host' => 'MoviePlz Demo']);

        foreach (['Reviewer A', 'Reviewer B'] as $name) {
            $this->flushSession();
            $this->post('/onboarding/invite', ['code' => '24680'])->assertOk()->assertSee('One more thing', false);
            $this->post('/onboarding/name', ['display_name' => $name])->assertOk()->assertSee("You're in", false);
        }

        $this->assertSame(2, Client::where('host_id', $demo->id)->where('role', 'client')->count());
    }

    public function test_the_review_code_does_nothing_when_not_configured(): void
    {
        config(['movieplz.review_code' => null]);

        $this->post('/onboarding/invite', ['code' => '24680'])->assertOk()->assertSee('Code not recognized', false);
    }
}
