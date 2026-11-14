<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Host;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'host_id' => Host::factory(),
            'invite_id' => null,
            'role' => 'client',
            'display_name' => $this->faker->firstName(),
            'device_token' => Str::random(40),
            'last_seen_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function host(): static
    {
        return $this->state(fn () => [
            'role' => 'host',
            'display_name' => 'Host',
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
        ]);
    }
}
