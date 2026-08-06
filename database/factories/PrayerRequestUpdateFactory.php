<?php

namespace Database\Factories;

use App\Models\PrayerRequest;
use App\Models\PrayerRequestUpdate;
use App\Models\User;
use App\PrayerRequestUpdateType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PrayerRequestUpdate> */
class PrayerRequestUpdateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'prayer_request_id' => PrayerRequest::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(PrayerRequestUpdateType::cases()),
            'note' => fake()->sentence(12),
            'is_private' => true,
        ];
    }
}
