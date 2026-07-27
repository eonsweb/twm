<?php

namespace Database\Seeders;

use App\Models\LeadershipPosition;
use App\Models\Person;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadershipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(LeadershipPositionSeeder::class);

        DB::transaction(function (): void {
            $positions = LeadershipPosition::query()->pluck('id', 'slug');

            foreach ($this->leaders() as $leader) {
                $person = Person::query()->firstOrCreate(
                    ['slug' => Str::slug($leader['name'])],
                    [
                        'user_id' => null,
                        'title' => $leader['title'],
                        'first_name' => $leader['first_name'],
                        'middle_name' => $leader['middle_name'],
                        'last_name' => $leader['last_name'],
                        'photo_path' => null,
                        'short_bio' => null,
                        'biography' => null,
                        'email' => null,
                        'phone' => null,
                        'website_url' => null,
                        'facebook_url' => null,
                        'instagram_url' => null,
                        'youtube_url' => null,
                        'is_active' => true,
                        'is_public' => true,
                    ],
                );

                foreach ($leader['positions'] as $index => $positionSlug) {
                    $person->leadershipAssignments()->firstOrCreate(
                        ['leadership_position_id' => $positions->get($positionSlug)],
                        [
                            'display_title' => $index === 0 ? $leader['display_title'] : null,
                            'started_at' => null,
                            'ended_at' => null,
                            'is_current' => true,
                            'is_primary' => $index === 0,
                            'sort_order' => $leader['sort_order'],
                        ],
                    );
                }
            }
        });
    }

    /**
     * @return list<array{
     *     name: string,
     *     title: string,
     *     first_name: string,
     *     middle_name: string|null,
     *     last_name: string,
     *     display_title: string,
     *     sort_order: int,
     *     positions: list<string>
     * }>
     */
    private function leaders(): array
    {
        return [
            [
                'name' => 'Prophet Joseph John Darko',
                'title' => 'Prophet',
                'first_name' => 'Joseph',
                'middle_name' => 'John',
                'last_name' => 'Darko',
                'display_title' => 'Founder & Lead Pastor',
                'sort_order' => 10,
                'positions' => ['founder', 'lead-pastor'],
            ],
            [
                'name' => 'Rev. Mrs. Asabea Ayisi Darko',
                'title' => 'Rev. Mrs.',
                'first_name' => 'Asabea',
                'middle_name' => 'Ayisi',
                'last_name' => 'Darko',
                'display_title' => 'Co-Founder',
                'sort_order' => 20,
                'positions' => ['co-founder'],
            ],
            [
                'name' => 'Rev. Maxwell Asante',
                'title' => 'Rev.',
                'first_name' => 'Maxwell',
                'middle_name' => null,
                'last_name' => 'Asante',
                'display_title' => 'Associate Pastor',
                'sort_order' => 30,
                'positions' => ['associate-pastor'],
            ],
            [
                'name' => 'Rev. Ishmael Sam',
                'title' => 'Rev.',
                'first_name' => 'Ishmael',
                'middle_name' => null,
                'last_name' => 'Sam',
                'display_title' => 'Associate Pastor',
                'sort_order' => 40,
                'positions' => ['associate-pastor'],
            ],
            [
                'name' => 'Rev. Francis Yeboah',
                'title' => 'Rev.',
                'first_name' => 'Francis',
                'middle_name' => null,
                'last_name' => 'Yeboah',
                'display_title' => 'Associate Pastor',
                'sort_order' => 50,
                'positions' => ['associate-pastor'],
            ],
            [
                'name' => 'Rev. Kwame Nkrumah Ampofo',
                'title' => 'Rev.',
                'first_name' => 'Kwame',
                'middle_name' => 'Nkrumah',
                'last_name' => 'Ampofo',
                'display_title' => 'Associate Pastor',
                'sort_order' => 60,
                'positions' => ['associate-pastor'],
            ],
        ];
    }
}
