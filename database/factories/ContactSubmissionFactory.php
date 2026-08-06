<?php

namespace Database\Factories;

use App\ContactSubmissionCategory;
use App\ContactSubmissionPriority;
use App\ContactSubmissionStatus;
use App\Models\ContactSubmission;
use App\Models\User;
use App\PreferredContactMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContactSubmission> */
class ContactSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return ['submission_token' => (string) Str::uuid(), 'duplicate_key' => hash('sha256', fake()->unique()->uuid()), 'name' => fake()->name(), 'email' => fake()->safeEmail(), 'phone' => fake()->optional()->e164PhoneNumber(), 'subject' => fake()->sentence(5), 'category' => fake()->randomElement(ContactSubmissionCategory::cases()), 'message' => fake()->paragraphs(2, true), 'preferred_contact_method' => PreferredContactMethod::Email, 'status' => ContactSubmissionStatus::New, 'priority' => ContactSubmissionPriority::Normal, 'ip_address' => fake()->ipv4(), 'user_agent' => 'Factory test browser', 'source_page' => 'https://example.test/contact'];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['status' => ContactSubmissionStatus::Read, 'read_at' => now()]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => ['status' => ContactSubmissionStatus::InProgress, 'read_at' => now()]);
    }

    public function waitingForVisitor(): static
    {
        return $this->state(fn (): array => ['status' => ContactSubmissionStatus::WaitingForVisitor, 'read_at' => now(), 'admin_replied_at' => now()]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => ['status' => ContactSubmissionStatus::Resolved, 'read_at' => now(), 'resolved_at' => now(), 'resolved_by' => User::factory()]);
    }

    public function spam(): static
    {
        return $this->state(fn (): array => ['status' => ContactSubmissionStatus::Spam]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (): array => ['priority' => ContactSubmissionPriority::High]);
    }

    public function assigned(): static
    {
        return $this->state(fn (): array => ['assigned_to' => User::factory(), 'status' => ContactSubmissionStatus::InProgress, 'read_at' => now()]);
    }
}
