<?php

namespace Database\Factories;

use App\Models\EventType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<EventType> */
class EventTypeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = str(fake()->unique()->sentence(3))->trim('.')->title()->toString();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'color' => fake()->randomElement(['emerald', 'blue', 'amber', 'rose', 'violet']),
            'icon' => 'calendar-days',
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
