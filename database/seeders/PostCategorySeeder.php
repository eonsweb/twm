<?php

namespace Database\Seeders;

use App\Models\PostCategory;
use Illuminate\Database\Seeder;

class PostCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Church News', 'Announcements', 'Devotionals', 'Teachings', 'Community Outreach', 'Ministry Updates', 'Testimonies', 'Events', 'Anniversary'] as $order => $name) {
            PostCategory::query()->updateOrCreate(
                ['slug' => str($name)->slug()->toString()],
                ['name' => $name, 'description' => "Articles and updates about {$name}.", 'is_active' => true, 'sort_order' => $order],
            );
        }
    }
}
