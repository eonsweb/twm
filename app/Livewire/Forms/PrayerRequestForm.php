<?php

namespace App\Livewire\Forms;

use App\AccountStatus;
use App\Models\PrayerRequest;
use App\Models\User;
use App\PermissionName;
use App\PrayerRequestCategory;
use App\PrayerRequestPriority;
use App\PrayerRequestPrivacy;
use App\PrayerRequestSource;
use App\PrayerRequestStatus;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class PrayerRequestForm extends Form
{
    public ?int $prayerRequestId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $country = '';

    public string $city = '';

    public string $subject = '';

    public string $request = '';

    public string $category = '';

    public string $privacyLevel = 'private';

    public string $status = 'new';

    public string $priority = 'normal';

    public int|string|null $assignedTo = null;

    public bool $isAnonymous = false;

    public bool $allowContact = false;

    public bool $allowPublication = false;

    public string $adminNotes = '';

    public string $source = 'admin';

    public string $publicTitle = '';

    public string $publicExcerpt = '';

    public string $publicContent = '';

    public bool $termsAccepted = false;

    public function setPrayerRequest(PrayerRequest $prayerRequest): void
    {
        $this->prayerRequestId = $prayerRequest->id;
        $this->name = $prayerRequest->name ?? '';
        $this->email = $prayerRequest->email ?? '';
        $this->phone = $prayerRequest->phone ?? '';
        $this->country = $prayerRequest->country ?? '';
        $this->city = $prayerRequest->city ?? '';
        $this->subject = $prayerRequest->subject;
        $this->request = $prayerRequest->request;
        $this->category = isset($prayerRequest->category) ? $prayerRequest->category->value : '';
        $this->privacyLevel = $prayerRequest->privacy_level->value;
        $this->status = $prayerRequest->status->value;
        $this->priority = $prayerRequest->priority->value;
        $this->assignedTo = $prayerRequest->assigned_to;
        $this->isAnonymous = $prayerRequest->is_anonymous;
        $this->allowContact = $prayerRequest->allow_contact;
        $this->allowPublication = $prayerRequest->allow_publication;
        $this->adminNotes = $prayerRequest->admin_notes ?? '';
        $this->source = isset($prayerRequest->source) ? $prayerRequest->source->value : PrayerRequestSource::Admin->value;
        $this->publicTitle = $prayerRequest->public_title ?? '';
        $this->publicExcerpt = $prayerRequest->public_excerpt ?? '';
        $this->publicContent = $prayerRequest->public_content ?? '';
        $this->termsAccepted = true;
    }

    public function normalize(): void
    {
        foreach (['name', 'email', 'phone', 'country', 'city', 'subject', 'adminNotes', 'publicTitle', 'publicExcerpt'] as $property) {
            $this->{$property} = Str::squish($this->{$property});
        }

        $this->email = Str::lower($this->email);
        $this->assignedTo = filled($this->assignedTo) ? (int) $this->assignedTo : null;
        if ($this->isAnonymous) {
            $this->name = '';
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(bool $public = false): array
    {
        $rules = [
            'name' => [Rule::requiredIf(! $this->isAnonymous), 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64', 'regex:/^[0-9+().\s-]*$/'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'subject' => ['required', 'string', 'min:3', 'max:255'],
            'request' => ['required', 'string', 'min:20', 'max:20000'],
            'category' => ['nullable', Rule::enum(PrayerRequestCategory::class)],
            'privacyLevel' => ['required', Rule::enum(PrayerRequestPrivacy::class)],
            'isAnonymous' => ['boolean'],
            'allowContact' => ['boolean'],
            'allowPublication' => ['boolean'],
            'termsAccepted' => [$public ? 'accepted' : 'boolean'],
        ];

        if (! $public) {
            $rules += [
                'status' => ['required', Rule::enum(PrayerRequestStatus::class)],
                'priority' => ['required', Rule::enum(PrayerRequestPriority::class)],
                'assignedTo' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
                'adminNotes' => ['nullable', 'string', 'max:20000'],
                'source' => ['required', Rule::enum(PrayerRequestSource::class)],
                'publicTitle' => ['nullable', 'string', 'max:255'],
                'publicExcerpt' => ['nullable', 'string', 'max:1000'],
                'publicContent' => ['nullable', 'string', 'max:20000'],
            ];
        }

        return $rules;
    }

    public function validateBusinessRules(bool $public = false): void
    {
        $errors = [];

        if ($this->allowContact && ! filled($this->email) && ! filled($this->phone)) {
            $errors['form.email'] = __('Provide an email address or phone number if contact is allowed.');
        }

        if ($this->privacyLevel === PrayerRequestPrivacy::Public->value && ! $this->allowPublication) {
            $errors['form.allowPublication'] = __('Publication consent is required when public visibility is requested.');
        }

        if (! $public && $this->assignedTo !== null) {
            $eligible = User::query()->whereKey($this->assignedTo)
                ->where('account_status', AccountStatus::Active)
                ->permission(PermissionName::PrayerRequestsUpdate->value)
                ->exists();
            if (! $eligible) {
                $errors['form.assignedTo'] = __('Choose an active user who is permitted to manage prayer requests.');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return collect([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'city' => $this->city,
            'subject' => $this->subject,
            'request' => trim($this->request),
            'category' => $this->category,
            'submission_type' => $this->isAnonymous ? 'anonymous' : 'identified',
            'privacy_level' => $this->privacyLevel,
            'status' => $this->status,
            'priority' => $this->priority,
            'assigned_to' => $this->assignedTo,
            'is_anonymous' => $this->isAnonymous,
            'allow_contact' => $this->allowContact,
            'allow_publication' => $this->allowPublication,
            'admin_notes' => $this->adminNotes,
            'source' => $this->source,
            'public_title' => $this->publicTitle,
            'public_excerpt' => $this->publicExcerpt,
            'public_content' => trim($this->publicContent),
        ])->map(fn (mixed $value): mixed => $value === '' ? null : $value)->all();
    }
}
