<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\PostStatus;
use App\PostVisibility;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'author_id' => User::factory(),
            'post_category_id' => PostCategory::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(18),
            'content' => '<p>'.fake()->paragraph().'</p><h2>Faith in action</h2><p>'.fake()->paragraph().'</p>',
            'featured_image_alt_text' => null,
            'status' => PostStatus::Draft,
            'visibility' => PostVisibility::Public,
            'is_featured' => false,
            'allow_comments' => false,
            'published_at' => null,
            'scheduled_for' => null,
            'archived_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => PostStatus::Published, 'published_at' => now()->subDay()]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['status' => PostStatus::Scheduled, 'scheduled_for' => now()->addDay()]);
    }

    public function due(): static
    {
        return $this->state(fn (): array => ['status' => PostStatus::Scheduled, 'scheduled_for' => now()->subMinute()]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => PostStatus::Archived, 'archived_at' => now()]);
    }

    public function private(): static
    {
        return $this->state(fn (): array => ['visibility' => PostVisibility::Private]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
