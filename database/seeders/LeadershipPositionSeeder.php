<?php

namespace Database\Seeders;

use App\Models\LeadershipPosition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LeadershipPositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->positions() as $position) {
            LeadershipPosition::query()->firstOrCreate(
                ['slug' => Str::slug($position['name'])],
                $position,
            );
        }
    }

    /**
     * @return list<array{name: string, description: null, rank: int, is_active: bool}>
     */
    private function positions(): array
    {
        return [
            ['name' => 'Founder', 'description' => null, 'rank' => 10, 'is_active' => true],
            ['name' => 'Co-Founder', 'description' => null, 'rank' => 20, 'is_active' => true],
            ['name' => 'Lead Pastor', 'description' => null, 'rank' => 30, 'is_active' => true],
            ['name' => 'Associate Pastor', 'description' => null, 'rank' => 40, 'is_active' => true],
            ['name' => 'Deacon', 'description' => null, 'rank' => 60, 'is_active' => true],
            ['name' => 'Deaconess', 'description' => null, 'rank' => 70, 'is_active' => true],
        ];
    }
}
