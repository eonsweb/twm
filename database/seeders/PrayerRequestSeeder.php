<?php

namespace Database\Seeders;

use App\Models\PrayerRequest;
use App\PrayerRequestCategory;
use App\PrayerRequestPriority;
use App\PrayerRequestPrivacy;
use App\PrayerRequestSource;
use App\PrayerRequestStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PrayerRequestSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['PR-DEMO-0001', 'Wisdom for a family decision', PrayerRequestCategory::Family, PrayerRequestStatus::New, PrayerRequestPriority::Normal],
            ['PR-DEMO-0002', 'Recovery after surgery', PrayerRequestCategory::Healing, PrayerRequestStatus::Praying, PrayerRequestPriority::High],
            ['PR-DEMO-0003', 'Peace during a difficult season', PrayerRequestCategory::Guidance, PrayerRequestStatus::UnderReview, PrayerRequestPriority::Normal],
            ['PR-DEMO-0004', 'New employment opportunity', PrayerRequestCategory::Career, PrayerRequestStatus::Answered, PrayerRequestPriority::Low],
        ];

        foreach ($samples as [$reference, $subject, $category, $status, $priority]) {
            PrayerRequest::query()->firstOrCreate(['reference_number' => $reference], [
                'public_token' => (string) Str::ulid(),
                'subject' => $subject,
                'request' => 'This is fictional demonstration content for local development and testing only.',
                'category' => $category,
                'status' => $status,
                'priority' => $priority,
                'privacy_level' => PrayerRequestPrivacy::Private,
                'source' => PrayerRequestSource::Admin,
                'is_anonymous' => true,
            ]);
        }
    }
}
