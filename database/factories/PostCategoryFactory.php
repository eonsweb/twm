<?php

namespace Database\Factories;

use App\Models\PostCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PostCategory> */
class PostCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->sentence(2);

        return ['name' => Str::title($name), 'slug' => Str::slug($name), 'description' => fake()->sentence(), 'is_active' => true, 'sort_order' => 0];
    }
}
