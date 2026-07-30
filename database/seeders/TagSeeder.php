<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Faith', 'Prayer', 'Worship', 'Community', 'Youth', 'Leadership', 'Outreach', 'Testimony'] as $name) {
            Tag::query()->updateOrCreate(['slug' => str($name)->slug()->toString()], ['name' => $name]);
        }
    }
}
