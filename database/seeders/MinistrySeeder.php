<?php

namespace Database\Seeders;

use App\MinistryStatus;
use App\Models\Ministry;
use App\Models\Person;
use Illuminate\Database\Seeder;

class MinistrySeeder extends Seeder
{
    public function run(): void
    {
        $ministries = [
            ['Children’s Ministry', 'Helping children know Jesus, grow in faith, and flourish in a safe community.', 'Sunday', '09:00'],
            ['Youth Ministry', 'Equipping young people to live boldly for Christ and influence their generation.', 'Friday', '18:00'],
            ['Women’s Ministry', 'Nurturing women through prayer, fellowship, discipleship, and practical support.', 'Saturday', '10:00'],
            ['The Gathering of Kingdom Brothers', 'Building godly men who lead faithfully at home, church, and work.', 'Saturday', '07:00'],
            ['Music Ministry', 'Leading the church in Christ-centred worship with excellence and humility.', 'Thursday', '18:00'],
            ['Voice of Triumph', 'A worship fellowship using music to proclaim the victory of Christ.', 'Wednesday', '18:00'],
            ['Media Ministry', 'Extending the gospel through sound, video, design, and digital media.', 'Saturday', '15:00'],
            ['Prayer Ministry', 'Mobilising the church for persistent, scripture-led prayer.', 'Tuesday', '18:00'],
            ['Evangelism Ministry', 'Sharing the gospel and helping new believers become disciples.', 'Saturday', '08:00'],
            ['Ushering Ministry', 'Welcoming and serving worshippers with warmth, order, and care.', 'Sunday', '07:30'],
            ['Welfare Ministry', 'Providing compassionate and practical support to people in need.', 'Sunday', '12:30'],
        ];
        $leaders = Person::query()->leaders()->active()->orderBy('id')->get();

        foreach ($ministries as $index => [$name, $description, $day, $time]) {
            $ministry = Ministry::withTrashed()->updateOrCreate(
                ['slug' => str($name)->slug()->toString()],
                [
                    'name' => $name,
                    'short_description' => $description,
                    'description' => $description,
                    'mission' => 'To help people encounter Christ, grow as disciples, and serve with purpose.',
                    'vision' => 'A thriving ministry community that transforms lives and advances God’s kingdom.',
                    'meeting_day' => $day,
                    'meeting_time' => $time,
                    'meeting_location' => 'Triumphant World Ministry, Main Campus',
                    'display_order' => $index + 1,
                    'status' => MinistryStatus::Published,
                    'is_featured' => $index < 4,
                    'published_at' => now()->subDays(30 - $index),
                    'deleted_at' => null,
                ],
            );

            if ($leaders->isNotEmpty()) {
                $leader = $leaders[$index % $leaders->count()];
                $ministry->leaders()->syncWithoutDetaching([
                    $leader->id => ['role_title' => 'Ministry Head', 'is_primary' => true, 'display_order' => 0],
                ]);
            }
        }
    }
}
