<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'user_id' => null,
            'title' => fake()->optional()->randomElement(['Rev.', 'Pastor', 'Prophet']),
            'first_name' => $firstName,
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => $lastName,
            'slug' => Str::slug($firstName.' '.$lastName.' '.fake()->unique()->randomNumber()),
            'photo_path' => null,
            'short_bio' => fake()->optional()->sentence(),
            'biography' => fake()->optional()->paragraph(),
            'email' => null,
            'phone' => null,
            'website_url' => null,
            'facebook_url' => null,
            'instagram_url' => null,
            'youtube_url' => null,
            'is_active' => true,
            'is_public' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => false,
        ]);
    }
}
