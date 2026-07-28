<?php

namespace Database\Factories;

use App\Models\SermonSeries;
use App\SermonSeriesStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SermonSeries>
 */
class SermonSeriesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->company();

        return [
            'title' => Str::title($title),
            'slug' => Str::slug($title.'-'.fake()->unique()->randomNumber()),
            'description' => fake()->paragraph(),
            'starts_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'ends_at' => null,
            'status' => SermonSeriesStatus::Draft,
            'is_featured' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => SermonSeriesStatus::Published]);
    }
}
