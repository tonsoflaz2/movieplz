<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Host;
use App\Models\MediaRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MediaRequest>
 */
class MediaRequestFactory extends Factory
{
    protected $model = MediaRequest::class;

    public function definition(): array
    {
        return [
            'host_id' => Host::factory(),
            'client_id' => Client::factory(),
            'title' => rtrim($this->faker->sentence(3), '.'),
            'year' => $this->faker->optional()->numberBetween(1970, 2026),
            'type' => $this->faker->optional()->randomElement(['film', 'series']),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'status' => 'pending',
        ];
    }

    public function fulfilled(): static
    {
        return $this->state(fn () => [
            'status' => MediaRequest::STATUS_FULFILLED,
            'fulfilled_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn () => [
            'status' => MediaRequest::STATUS_DECLINED,
        ]);
    }
}
