<?php

namespace Database\Factories;

use App\Models\LeadershipAssignment;
use App\Models\LeadershipPosition;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadershipAssignment>
 */
class LeadershipAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'leadership_position_id' => LeadershipPosition::factory(),
            'display_title' => null,
            'started_at' => null,
            'ended_at' => null,
            'is_current' => true,
            'is_primary' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function former(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_current' => false,
            'ended_at' => now()->subDay(),
        ]);
    }
}
