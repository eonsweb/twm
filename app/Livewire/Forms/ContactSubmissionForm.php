<?php

namespace App\Livewire\Forms;

use App\ContactSubmissionCategory;
use App\PreferredContactMethod;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ContactSubmissionForm extends Form
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $category = 'general';

    public string $subject = '';

    public string $message = '';

    public string $preferredContactMethod = 'email';

    public bool $consent = false;

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]*$/'],
            'category' => ['required', Rule::enum(ContactSubmissionCategory::class)],
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'preferredContactMethod' => ['required', Rule::enum(PreferredContactMethod::class)],
            'consent' => ['accepted'],
        ];
    }

    public function normalize(): void
    {
        $this->name = Str::squish($this->name);
        $this->email = Str::lower(Str::squish($this->email));
        $this->phone = Str::squish($this->phone);
        $this->subject = Str::squish($this->subject);
        $this->message = trim($this->message);
    }

    public function validateContactMethod(): void
    {
        if (in_array($this->preferredContactMethod, [PreferredContactMethod::Phone->value, PreferredContactMethod::Whatsapp->value], true) && blank($this->phone)) {
            throw ValidationException::withMessages(['form.phone' => __('A phone number is required for the selected contact method.')]);
        }
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return ['name' => $this->name, 'email' => $this->email, 'phone' => $this->phone ?: null, 'category' => $this->category, 'subject' => $this->subject, 'message' => $this->message, 'preferred_contact_method' => $this->preferredContactMethod];
    }
}
