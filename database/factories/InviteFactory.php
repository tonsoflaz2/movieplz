<?php

namespace Database\Factories;

use App\Models\Host;
use App\Models\Invite;
use App\Support\InviteCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invite>
 */
class InviteFactory extends Factory
{
    protected $model = Invite::class;

    public function definition(): array
    {
        return [
            'host_id' => Host::factory(),
            'code' => str_pad((string) $this->faker->unique()->numberBetween(0, 99999), 5, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(InviteCode::LIFETIME_MINUTES),
            'grants_host' => false,
        ];
    }

    public function hostBootstrap(): static
    {
        return $this->state(fn () => [
            'host_id' => null,
            'grants_host' => true,
        ]);
    }

    public function redeemed(): static
    {
        return $this->state(fn () => [
            'redeemed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
