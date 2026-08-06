<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageSection> */
class PageSectionFactory extends Factory
{
    public function definition(): array
    {
        return ['page_id' => Page::factory(), 'section_type' => 'rich-text', 'name' => fake()->words(3, true), 'heading' => fake()->sentence(4), 'content' => '<p>'.fake()->paragraph().'</p>', 'settings' => [], 'sort_order' => 0, 'is_visible' => true];
    }
}
