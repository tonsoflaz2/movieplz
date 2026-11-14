<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Host;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HostSeeder extends Seeder
{
    public function run(): void
    {
        // The single host row + the host's clients row (role='host').
        // In production the host would be claimed via `php artisan
        // host:mint-code`. For local dev we create it directly so
        // tinker / tests / smoke runs don't need a round-trip through
        // the onboarding flow.
        $host = Host::firstOrCreate(
            ['id' => 1],
            ['name' => 'Home library']
        );

        Client::firstOrCreate(
            ['host_id' => $host->id, 'role' => 'host'],
            [
                'display_name' => 'Host',
                'device_token' => Str::random(40),
                'last_seen_at' => now(),
            ]
        );
    }
}
