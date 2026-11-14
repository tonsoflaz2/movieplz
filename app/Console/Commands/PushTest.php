<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\PushService;
use Illuminate\Console\Command;

class PushTest extends Command
{
    protected $signature = 'push:test {--client-id= : Client ID to push to (default: first client with a token)}';
    protected $description = 'Send a test push notification to verify APNs is working';

    public function handle(): int
    {
        $query = Client::query()->whereNotNull('push_token')->where('push_token', '!=', '');

        if ($id = $this->option('client-id')) {
            $query->where('id', $id);
        }

        $client = $query->first();

        if (! $client) {
            $this->error('No client with a push token found. Open the app on a real device first.');
            return 1;
        }

        $this->info("Sending test push to {$client->display_name} (id={$client->id}, platform={$client->push_platform})");
        $this->info("Token: {$client->push_token}");

        PushService::send(
            $client->push_token,
            $client->push_platform ?? 'ios',
            'Test from MoviePlz',
            'If you see this, push works!',
            ['route' => '/home.ham'],
            ['client' => $client->id, 'event' => 'test'],
        );

        $this->info('Sent. Check the device and storage/logs/laravel.log for errors.');
        return 0;
    }
}
