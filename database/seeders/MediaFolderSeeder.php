<?php

namespace Database\Seeders;

use App\Models\MediaFolder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MediaFolderSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Branding', 'Events', 'Ministries', 'Sermons', 'Website'] as $name) {
            MediaFolder::query()->firstOrCreate(
                ['parent_id' => null, 'name' => $name],
                ['slug' => str($name)->slug()->toString()],
            );
        }
    }
}
