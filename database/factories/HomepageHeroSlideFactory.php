<?php

namespace Database\Factories;

use App\Models\HomepageHeroSlide;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HomepageHeroSlide> */
class HomepageHeroSlideFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'media_id' => Media::factory(),
            'settings' => ['variant' => 'default'],
        ];
    }
}
