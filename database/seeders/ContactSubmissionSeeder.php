<?php

namespace Database\Seeders;

use App\ContactSubmissionCategory;
use App\ContactSubmissionPriority;
use App\ContactSubmissionStatus;
use App\Models\ContactSubmission;
use App\PreferredContactMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContactSubmissionSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['Membership information', ContactSubmissionCategory::Membership, ContactSubmissionStatus::New, ContactSubmissionPriority::Normal],
            ['Counselling appointment enquiry', ContactSubmissionCategory::Counselling, ContactSubmissionStatus::InProgress, ContactSubmissionPriority::High],
            ['Upcoming conference information', ContactSubmissionCategory::Event, ContactSubmissionStatus::WaitingForVisitor, ContactSubmissionPriority::Normal],
            ['Joining a ministry team', ContactSubmissionCategory::Ministry, ContactSubmissionStatus::Read, ContactSubmissionPriority::Low],
            ['Website accessibility feedback', ContactSubmissionCategory::WebsiteSupport, ContactSubmissionStatus::Resolved, ContactSubmissionPriority::Normal],
            ['Giving methods enquiry', ContactSubmissionCategory::Giving, ContactSubmissionStatus::Closed, ContactSubmissionPriority::Normal],
        ];
        foreach ($samples as $index => [$subject, $category, $status, $priority]) {
            ContactSubmission::query()->firstOrCreate(['duplicate_key' => hash('sha256', 'contact-demo-'.$index)], ['reference_number' => sprintf('TWM-CON-DEMO-%04d', $index + 1), 'public_token' => (string) Str::ulid(), 'submission_token' => sprintf('00000000-0000-4000-8000-%012d', $index + 1), 'name' => 'Demo Visitor '.($index + 1), 'email' => 'visitor'.($index + 1).'@example.test', 'subject' => $subject, 'category' => $category, 'message' => 'This is fictional demonstration content for local development only. Please provide more information about this enquiry.', 'preferred_contact_method' => PreferredContactMethod::Email, 'status' => $status, 'priority' => $priority, 'read_at' => $status === ContactSubmissionStatus::New ? null : now()->subDays($index), 'resolved_at' => $status === ContactSubmissionStatus::Resolved ? now()->subDay() : null, 'created_at' => now()->subDays($index + 1)]);
        }
    }
}
