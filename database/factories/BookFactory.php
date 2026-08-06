<?php

namespace Database\Factories;

use App\BookAvailabilityStatus;
use App\BookFormat;
use App\BookStatus;
use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Book> */
class BookFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'subtitle' => fake()->optional()->sentence(6),
            'author_name' => fake()->name(),
            'short_description' => fake()->sentence(18),
            'description' => fake()->paragraphs(4, true),
            'isbn' => fake()->optional()->isbn13(),
            'publisher' => fake()->company(),
            'publication_date' => fake()->dateTimeBetween('-10 years', '+1 year'),
            'edition' => fake()->optional()->randomElement(['First edition', 'Second edition', 'Revised edition']),
            'language' => 'English',
            'page_count' => fake()->numberBetween(80, 500),
            'format' => BookFormat::Physical,
            'price' => fake()->randomFloat(2, 10, 250),
            'currency' => 'GHS',
            'stock_quantity' => fake()->numberBetween(0, 100),
            'availability_status' => BookAvailabilityStatus::Available,
            'purchase_url' => fake()->url(),
            'download_url' => null,
            'is_featured' => false,
            'is_free' => false,
            'status' => BookStatus::Draft,
            'published_at' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => BookStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => BookStatus::Published,
            'published_at' => now()->addDay(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => BookStatus::Archived,
            'is_featured' => false,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }

    public function free(): static
    {
        return $this->state(fn (): array => [
            'is_free' => true,
            'price' => null,
            'format' => BookFormat::Ebook,
            'download_url' => fake()->url(),
        ]);
    }
}
