<?php

namespace Database\Factories;

use App\Models\ServiceSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceSchedule>
 */
class ServiceScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Sunday Worship', 'Prayer Meeting', 'Bible Study']),
            'day_of_week' => fake()->randomElement(['Sunday', 'Wednesday', 'Friday']),
            'start_time' => fake()->time('H:i'),
            'end_time' => null,
            'location' => fake()->streetAddress(),
            'description' => fake()->sentence(),
            'display_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
