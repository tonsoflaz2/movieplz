<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Host;
use App\Models\Invite;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use App\Support\InviteCode;
use App\Support\SortTitle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Populates the dev database with realistic-looking data:
 *   - ~80 library items from database/fixtures/library_seed.json
 *   - 5 fake clients tied to the seeded host
 *   - 1 active invite code for manual smoke testing on a second phone
 *   - 10 requests in mixed states (pending, fulfilled, declined)
 *
 * Only runs in local / testing environments.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $host = Host::query()->orderBy('id')->firstOrFail();

        $this->seedLibrary($host);
        $this->seedClientsAndInvite($host);
        $this->seedRequests($host);
    }

    private function seedLibrary(Host $host): void
    {
        $path = database_path('fixtures/library_seed.json');
        $json = json_decode(file_get_contents($path), true);

        foreach ($json as $item) {
            LibraryItem::firstOrCreate(
                [
                    'host_id' => $host->id,
                    'title' => $item['title'],
                    'year' => $item['year'],
                ],
                [
                    'sort_title' => SortTitle::from($item['title']),
                    'type' => $item['type'],
                    'added_at' => now()->subDays(random_int(1, 365)),
                ]
            );
        }
    }

    private function seedClientsAndInvite(Host $host): void
    {
        $names = ['Alice', 'Bob', 'Carol', 'Dave', 'Eve'];

        foreach ($names as $name) {
            $invite = Invite::create([
                'host_id' => $host->id,
                'code' => InviteCode::generate(),
                'expires_at' => now()->subMinutes(random_int(30, 10_000)),
                'redeemed_at' => now()->subDays(random_int(1, 30)),
                'grants_host' => false,
            ]);

            $client = Client::create([
                'host_id' => $host->id,
                'invite_id' => $invite->id,
                'role' => 'client',
                'display_name' => $name,
                'device_token' => Str::random(40),
                'last_seen_at' => now()->subHours(random_int(1, 48)),
            ]);

            $invite->update(['redeemed_by_client_id' => $client->id]);
        }

        // One live, unredeemed code the host can actually hand to a tester.
        Invite::create([
            'host_id' => $host->id,
            'code' => '12345', // deterministic for easy manual testing
            'expires_at' => now()->addMinutes(InviteCode::LIFETIME_MINUTES),
            'grants_host' => false,
        ]);
    }

    private function seedRequests(Host $host): void
    {
        $clients = Client::query()
            ->where('host_id', $host->id)
            ->where('role', 'client')
            ->get();

        // Pool of plausible "not in the library yet" titles.
        $wishlist = [
            ['title' => 'Poor Things', 'year' => 2023, 'type' => 'film'],
            ['title' => 'The Zone of Interest', 'year' => 2023, 'type' => 'film'],
            ['title' => 'Past Lives', 'year' => 2023, 'type' => 'film'],
            ['title' => 'Anatomy of a Fall', 'year' => 2023, 'type' => 'film'],
            ['title' => 'The Holdovers', 'year' => 2023, 'type' => 'film'],
            ['title' => 'Fallout', 'year' => 2024, 'type' => 'series'],
            ['title' => 'Baby Reindeer', 'year' => 2024, 'type' => 'series'],
            ['title' => 'Ripley', 'year' => 2024, 'type' => 'series'],
            ['title' => 'Slow Horses', 'year' => 2022, 'type' => 'series'],
            ['title' => 'Pachinko', 'year' => 2022, 'type' => 'series'],
        ];

        // 5 pending, 3 fulfilled (linked to a real library item), 2 declined.
        $libraryItem = LibraryItem::query()->where('host_id', $host->id)->first();

        foreach (array_slice($wishlist, 0, 5) as $w) {
            MediaRequest::create([
                'host_id' => $host->id,
                'client_id' => $clients->random()->id,
                'title' => $w['title'],
                'year' => $w['year'],
                'type' => $w['type'],
                'status' => MediaRequest::STATUS_PENDING,
            ]);
        }

        foreach (array_slice($wishlist, 5, 3) as $w) {
            MediaRequest::create([
                'host_id' => $host->id,
                'client_id' => $clients->random()->id,
                'title' => $w['title'],
                'year' => $w['year'],
                'type' => $w['type'],
                'status' => MediaRequest::STATUS_FULFILLED,
                'fulfilled_at' => now()->subHours(random_int(1, 72)),
                'fulfilled_library_item_id' => $libraryItem?->id,
            ]);
        }

        foreach (array_slice($wishlist, 8, 2) as $w) {
            MediaRequest::create([
                'host_id' => $host->id,
                'client_id' => $clients->random()->id,
                'title' => $w['title'],
                'year' => $w['year'],
                'type' => $w['type'],
                'status' => MediaRequest::STATUS_DECLINED,
            ]);
        }
    }
}
