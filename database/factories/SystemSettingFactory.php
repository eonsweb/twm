<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use App\SettingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => 'general',
            'key' => fake()->unique()->slug(2),
            'value' => fake()->sentence(),
            'type' => SettingType::String,
            'label' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_public' => false,
            'is_encrypted' => false,
        ];
    }
}
