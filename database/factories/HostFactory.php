<?php

namespace Database\Factories;

use App\Models\Host;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Host>
 */
class HostFactory extends Factory
{
    protected $model = Host::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->firstName()."'s library",
        ];
    }
}
