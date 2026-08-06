<?php

namespace Database\Factories;

use App\Models\PrayerRequest;
use App\Models\User;
use App\PrayerRequestCategory;
use App\PrayerRequestPriority;
use App\PrayerRequestPrivacy;
use App\PrayerRequestSource;
use App\PrayerRequestStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PrayerRequest> */
class PrayerRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'country' => 'Ghana',
            'city' => fake()->city(),
            'subject' => fake()->sentence(5),
            'request' => fake()->paragraphs(2, true),
            'category' => fake()->randomElement(PrayerRequestCategory::cases()),
            'submission_type' => 'identified',
            'privacy_level' => PrayerRequestPrivacy::PrayerTeam,
            'status' => PrayerRequestStatus::New,
            'priority' => PrayerRequestPriority::Normal,
            'is_anonymous' => false,
            'allow_contact' => true,
            'allow_publication' => false,
            'is_published' => false,
            'source' => PrayerRequestSource::Website,
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn (): array => ['name' => null, 'email' => null, 'phone' => null, 'submission_type' => 'anonymous', 'is_anonymous' => true, 'allow_contact' => false]);
    }

    public function urgent(): static
    {
        return $this->state(fn (): array => ['priority' => PrayerRequestPriority::Urgent]);
    }

    public function assigned(?User $user = null): static
    {
        return $this->state(fn (): array => ['assigned_to' => $user === null ? User::factory() : $user->id, 'status' => PrayerRequestStatus::Assigned]);
    }

    public function answered(): static
    {
        return $this->state(fn (): array => ['status' => PrayerRequestStatus::Answered, 'answered_at' => now()->subDay()]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => PrayerRequestStatus::Archived, 'is_published' => false]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'privacy_level' => PrayerRequestPrivacy::Public,
            'allow_publication' => true,
            'is_published' => true,
            'published_at' => now()->subDay(),
            'public_title' => fake()->sentence(5),
            'public_excerpt' => fake()->sentence(15),
            'public_content' => fake()->paragraphs(2, true),
        ]);
    }
}
