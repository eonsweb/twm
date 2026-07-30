<?php

namespace Database\Factories;

use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaFolder>
 */
class MediaFolderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = fake()->unique()->words(2);
        $name = is_array($words) ? implode(' ', $words) : $words;

        return [
            'parent_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }
}
