<?php

namespace Database\Factories;

use App\Models\Host;
use App\Models\LibraryItem;
use App\Support\SortTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LibraryItem>
 */
class LibraryItemFactory extends Factory
{
    protected $model = LibraryItem::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(3);
        $title = rtrim($title, '.');

        return [
            'host_id' => Host::factory(),
            'title' => $title,
            'sort_title' => SortTitle::from($title),
            'type' => $this->faker->randomElement(['film', 'series']),
            'year' => $this->faker->numberBetween(1970, 2026),
            'added_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
