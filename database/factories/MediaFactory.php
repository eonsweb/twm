<?php

namespace Database\Factories;

use App\MediaStatus;
use App\MediaType;
use App\MediaVisibility;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = fake()->words(3);
        $name = is_array($words) ? implode(' ', $words) : $words;
        $fileName = Str::uuid().'.jpg';

        return [
            'media_folder_id' => null,
            'uploaded_by' => User::factory(),
            'updated_by' => null,
            'name' => Str::title($name),
            'original_name' => Str::slug($name).'.jpg',
            'file_name' => $fileName,
            'slug' => Str::slug($name),
            'disk' => 'public',
            'directory' => 'media/images/'.now()->format('Y/m'),
            'path' => 'media/images/'.now()->format('Y/m').'/'.$fileName,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'media_type' => MediaType::Image,
            'size' => fake()->numberBetween(1024, 2_000_000),
            'width' => 1200,
            'height' => 800,
            'visibility' => MediaVisibility::Public,
            'status' => MediaStatus::Active,
            'is_featured' => false,
        ];
    }

    public function private(): static
    {
        return $this->state(fn (): array => [
            'disk' => 'local',
            'visibility' => MediaVisibility::Private,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => MediaStatus::Archived]);
    }
}
