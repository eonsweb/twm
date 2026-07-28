<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\Sermon;
use App\SermonMediaPlatform;
use App\SermonMediaType;
use App\SermonStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sermon>
 */
class SermonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'title' => $title,
            'slug' => Str::slug($title.'-'.fake()->unique()->randomNumber()),
            'summary' => fake()->paragraph(),
            'description' => fake()->paragraphs(3, true),
            'scripture_reference' => fake()->randomElement(['John 3:16', 'Romans 8:28', 'Psalm 23']),
            'sermon_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'duration_seconds' => fake()->numberBetween(1200, 5400),
            'external_media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'media_platform' => SermonMediaPlatform::YouTube,
            'media_type' => SermonMediaType::Video,
            'embed_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'external_thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
            'speaker_id' => Person::factory(),
            'status' => SermonStatus::Draft,
            'is_featured' => false,
            'display_order' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => SermonStatus::Published,
            'published_at' => now()->subMinute(),
            'scheduled_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => SermonStatus::Scheduled,
            'published_at' => null,
            'scheduled_at' => now()->addDay(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
