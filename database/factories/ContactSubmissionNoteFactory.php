<?php

namespace Database\Factories;

use App\Models\ContactSubmission;
use App\Models\ContactSubmissionNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactSubmissionNote> */
class ContactSubmissionNoteFactory extends Factory
{
    public function definition(): array
    {
        return ['contact_submission_id' => ContactSubmission::factory(), 'user_id' => User::factory(), 'note' => fake()->sentence(12)];
    }
}
