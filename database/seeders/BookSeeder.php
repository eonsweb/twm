<?php

namespace Database\Seeders;

use App\BookAvailabilityStatus;
use App\BookFormat;
use App\BookStatus;
use App\Models\Book;
use App\Models\Media;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->oldest('id')->first();
        $cover = Media::query()->images()->public()->active()->oldest('id')->first();
        $leader = Person::query()->leaders()->active()->oldest('id')->first();
        $speaker = Person::query()->active()->whereHas('sermons')->oldest('id')->first();

        $samples = [
            [
                'title' => 'Walking in Faith',
                'slug' => 'walking-in-faith',
                'author_name' => $leader === null ? 'TWM Leadership' : $leader->full_name,
                'leadership_id' => $leader?->id,
                'short_description' => 'A practical guide to living with trust, courage, and consistency in every season.',
                'format' => BookFormat::Physical,
                'price' => 75,
                'stock_quantity' => 40,
                'availability_status' => BookAvailabilityStatus::Available,
                'purchase_url' => 'https://example.com/books/walking-in-faith',
                'is_free' => false,
                'is_featured' => true,
            ],
            [
                'title' => 'Grace for Today',
                'slug' => 'grace-for-today',
                'author_name' => $speaker === null ? 'TWM Teaching Ministry' : $speaker->full_name,
                'speaker_id' => $speaker?->id,
                'short_description' => 'Daily reflections that point readers toward the sufficiency of God’s grace.',
                'format' => BookFormat::Ebook,
                'price' => null,
                'availability_status' => BookAvailabilityStatus::Available,
                'download_url' => 'https://example.com/books/grace-for-today',
                'is_free' => true,
                'is_featured' => false,
            ],
            [
                'title' => 'Building a Godly Home',
                'slug' => 'building-a-godly-home',
                'author_name' => 'Guest Author',
                'short_description' => 'Biblical principles for cultivating love, wisdom, prayer, and purpose at home.',
                'format' => BookFormat::PhysicalAndDigital,
                'price' => 110,
                'availability_status' => BookAvailabilityStatus::Preorder,
                'purchase_url' => 'https://example.com/books/building-a-godly-home',
                'is_free' => false,
                'is_featured' => false,
            ],
            [
                'title' => 'The Prayer Journey',
                'slug' => 'the-prayer-journey',
                'author_name' => 'TWM Prayer Ministry',
                'short_description' => 'An audiobook companion for developing a thoughtful and enduring prayer life.',
                'format' => BookFormat::Audiobook,
                'price' => 45,
                'availability_status' => BookAvailabilityStatus::ComingSoon,
                'purchase_url' => 'https://example.com/books/the-prayer-journey',
                'is_free' => false,
                'is_featured' => false,
            ],
            [
                'title' => 'Servant Leadership Notes',
                'slug' => 'servant-leadership-notes',
                'author_name' => $leader === null ? 'TWM Leadership' : $leader->full_name,
                'leadership_id' => $leader?->id,
                'short_description' => 'Working notes for leaders who want to serve with humility, integrity, and vision.',
                'format' => BookFormat::Physical,
                'price' => 35,
                'availability_status' => BookAvailabilityStatus::OutOfStock,
                'is_free' => false,
                'is_featured' => false,
                'status' => BookStatus::Archived,
            ],
            [
                'title' => 'Foundations of Discipleship',
                'slug' => 'foundations-of-discipleship',
                'author_name' => 'TWM Discipleship Ministry',
                'short_description' => 'A clear introduction to spiritual disciplines, Christian community, and mission.',
                'format' => BookFormat::Ebook,
                'price' => null,
                'availability_status' => BookAvailabilityStatus::Available,
                'download_url' => 'https://example.com/books/foundations-of-discipleship',
                'is_free' => true,
                'is_featured' => false,
                'status' => BookStatus::Draft,
            ],
        ];

        foreach ($samples as $sample) {
            $requestedStatus = $sample['status'] ?? BookStatus::Published;
            $status = $requestedStatus === BookStatus::Published && $cover === null
                ? BookStatus::Draft
                : $requestedStatus;

            Book::query()->firstOrCreate(
                ['slug' => $sample['slug']],
                [
                    ...$sample,
                    'description' => $sample['short_description'],
                    'language' => 'English',
                    'currency' => 'GHS',
                    'media_id' => $cover?->id,
                    'status' => $status,
                    'is_featured' => $status === BookStatus::Published && $sample['is_featured'],
                    'published_at' => $status === BookStatus::Published ? now()->subDays(7) : null,
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                ],
            );
        }
    }
}
