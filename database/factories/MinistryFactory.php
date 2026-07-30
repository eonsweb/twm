<?php

namespace Database\Factories;

use App\MinistryStatus;
use App\Models\Ministry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Ministry> */
class MinistryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Ministry';

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'short_description' => fake()->sentence(12),
            'description' => fake()->paragraphs(3, true),
            'mission' => fake()->sentence(16),
            'vision' => fake()->sentence(14),
            'meeting_day' => fake()->randomElement(['Sunday', 'Wednesday', 'Friday', 'Saturday']),
            'meeting_time' => fake()->randomElement(['09:00', '17:30', '18:00']),
            'meeting_location' => fake()->randomElement(['Main Auditorium', 'Fellowship Hall', 'Online']),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => '+233 '.fake()->numerify('## ### ####'),
            'display_order' => fake()->numberBetween(0, 20),
            'status' => MinistryStatus::Draft,
            'is_featured' => false,
            'published_at' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => MinistryStatus::Published, 'published_at' => now()->subDay()]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['status' => MinistryStatus::Published, 'published_at' => now()->addDay()]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => MinistryStatus::Inactive]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
