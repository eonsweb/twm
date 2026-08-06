<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\User;
use App\PageStatus;
use App\PageVisibility;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Page> */
class PageFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return ['title' => $title, 'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999), 'page_type' => 'standard', 'template' => 'default', 'excerpt' => fake()->sentence(), 'content' => '<p>'.fake()->paragraph().'</p>', 'status' => PageStatus::Draft, 'visibility' => PageVisibility::Public, 'is_homepage' => false, 'show_in_navigation' => false, 'robots_index' => true, 'robots_follow' => true, 'created_by' => User::factory(), 'updated_by' => User::factory()];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => PageStatus::Published, 'visibility' => PageVisibility::Public, 'published_at' => now()->subMinute()]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['status' => PageStatus::Scheduled, 'published_at' => now()->addHour()]);
    }

    public function due(): static
    {
        return $this->state(fn () => ['status' => PageStatus::Scheduled, 'published_at' => now()->subMinute()]);
    }
}
