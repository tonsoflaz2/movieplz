<?php

namespace Tests\Feature;

use Tests\TestCase;

class SitePagesTest extends TestCase
{
    public function test_privacy_and_support_pages_are_public(): void
    {
        config(['movieplz.support_email' => 'help@example.com']);

        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy')->assertSee('help@example.com')
            ->assertSee('Google Firebase Cloud Messaging')->assertSee('Apple Push Notification service');
        $this->get('/support')->assertOk()->assertSee('Join as a friend')->assertSee('help@example.com');
    }

    public function test_support_links_to_both_store_listings(): void
    {
        $this->get('/support')->assertOk()
            ->assertSee('href="https://apps.apple.com/us/app/movieplz/id6762552708"', false)
            ->assertSee('MoviePlz on the App Store')
            ->assertSee('href="https://play.google.com/store/apps/details?id=com.hypeapps.movieplz"', false)
            ->assertSee('MoviePlz on Google Play');
    }
}
