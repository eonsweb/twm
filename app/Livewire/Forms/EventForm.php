<?php

namespace App\Livewire\Forms;

use App\EventLocationType;
use App\EventRecurrence;
use App\EventStatus;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Ministry;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class EventForm extends Form
{
    public ?int $eventId = null;

    public string $title = '';

    public int|string|null $eventTypeId = null;

    public int|string|null $ministryId = null;

    public string $shortDescription = '';

    public string $description = '';

    public mixed $featuredImage = null;

    public bool $removeFeaturedImage = false;

    public string $startDate = '';

    public string $startTime = '09:00';

    public string $endDate = '';

    public string $endTime = '';

    public string $timezone = 'Africa/Accra';

    public bool $isAllDay = false;

    public bool $isRecurring = false;

    public string $recurrence = '';

    public string $customRecurrence = '';

    public string $locationType = 'physical';

    public string $venueName = '';

    public string $address = '';

    public string $city = '';

    public string $region = '';

    public string $country = 'Ghana';

    public string $locationUrl = '';

    public string $meetingUrl = '';

    public bool $registrationRequired = false;

    public string $registrationUrl = '';

    public string $registrationDeadline = '';

    public int|string|null $maximumAttendees = null;

    public string $contactName = '';

    public string $contactPhone = '';

    public string $contactEmail = '';

    public string $status = 'draft';

    public string $publishedAt = '';

    public bool $isFeatured = false;

    public bool $isLivestreamed = false;

    public function setEvent(Event $event): void
    {
        $start = $event->starts_at->setTimezone($event->timezone);
        $end = $event->ends_at?->setTimezone($event->timezone);
        $recurrence = $event->recurrence_rule ?? '';
        $knownRecurrence = EventRecurrence::tryFrom($recurrence);

        $this->eventId = $event->id;
        $this->title = $event->title;
        $this->eventTypeId = $event->event_type_id;
        $this->ministryId = $event->ministry_id;
        $this->shortDescription = $event->short_description ?? '';
        $this->description = $event->description ?? '';
        $this->startDate = $start->toDateString();
        $this->startTime = $start->format('H:i');
        $this->endDate = $end?->toDateString() ?? '';
        $this->endTime = $end?->format('H:i') ?? '';
        $this->timezone = $event->timezone;
        $this->isAllDay = $event->is_all_day;
        $this->isRecurring = $event->is_recurring;
        $this->recurrence = $knownRecurrence instanceof EventRecurrence
            ? $knownRecurrence->value
            : ($recurrence === '' ? '' : EventRecurrence::Custom->value);
        $this->customRecurrence = $knownRecurrence === null ? $recurrence : '';
        $this->locationType = $event->location_type->value;
        $this->venueName = $event->venue_name ?? '';
        $this->address = $event->address ?? '';
        $this->city = $event->city ?? '';
        $this->region = $event->region ?? '';
        $this->country = $event->country;
        $this->locationUrl = $event->location_url ?? '';
        $this->meetingUrl = $event->meeting_url ?? '';
        $this->registrationRequired = $event->registration_required;
        $this->registrationUrl = $event->registration_url ?? '';
        $this->registrationDeadline = $event->registration_deadline?->setTimezone($event->timezone)->format('Y-m-d\TH:i') ?? '';
        $this->maximumAttendees = $event->maximum_attendees;
        $this->contactName = $event->contact_name ?? '';
        $this->contactPhone = $event->contact_phone ?? '';
        $this->contactEmail = $event->contact_email ?? '';
        $this->status = $event->status->value;
        $this->publishedAt = $event->published_at?->format('Y-m-d\TH:i') ?? '';
        $this->isFeatured = $event->is_featured;
        $this->isLivestreamed = $event->is_livestreamed;
    }

    public function normalize(): void
    {
        $this->title = str($this->title)->squish()->toString();
        $this->eventTypeId = filled($this->eventTypeId) ? (int) $this->eventTypeId : null;
        $this->ministryId = filled($this->ministryId) ? (int) $this->ministryId : null;
        $this->maximumAttendees = filled($this->maximumAttendees) ? (int) $this->maximumAttendees : null;

        if ($this->isAllDay) {
            $this->startTime = '00:00';
            $this->endTime = $this->endDate === '' ? '' : '23:59';
        }

        if (! $this->isRecurring) {
            $this->recurrence = '';
            $this->customRecurrence = '';
        }

        if (! $this->registrationRequired) {
            $this->registrationUrl = '';
            $this->registrationDeadline = '';
            $this->maximumAttendees = null;
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'eventTypeId' => ['nullable', 'integer', Rule::exists(EventType::class, 'id')->where('is_active', true)],
            'ministryId' => ['nullable', 'integer', Rule::exists(Ministry::class, 'id')->withoutTrashed()],
            'shortDescription' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:100000'],
            'featuredImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'removeFeaturedImage' => ['boolean'],
            'startDate' => ['required', 'date'],
            'startTime' => [Rule::requiredIf(! $this->isAllDay), 'nullable', 'date_format:H:i'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'endTime' => [Rule::requiredIf($this->endDate !== '' && ! $this->isAllDay), 'nullable', 'date_format:H:i'],
            'timezone' => ['required', 'timezone'],
            'isAllDay' => ['boolean'],
            'isRecurring' => ['boolean'],
            'recurrence' => [Rule::requiredIf($this->isRecurring), 'nullable', Rule::enum(EventRecurrence::class)],
            'customRecurrence' => [Rule::requiredIf($this->recurrence === EventRecurrence::Custom->value), 'nullable', 'string', 'max:1000'],
            'locationType' => ['required', Rule::enum(EventLocationType::class)],
            'venueName' => [Rule::requiredIf(in_array($this->locationType, ['physical', 'hybrid'], true)), 'nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'locationUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'meetingUrl' => [Rule::requiredIf(in_array($this->locationType, ['online', 'hybrid'], true)), 'nullable', 'url:https', 'max:2048'],
            'registrationRequired' => ['boolean'],
            'registrationUrl' => [Rule::requiredIf($this->registrationRequired), 'nullable', 'url:https', 'max:2048'],
            'registrationDeadline' => ['nullable', 'date'],
            'maximumAttendees' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'contactName' => ['nullable', 'string', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:64'],
            'contactEmail' => ['nullable', 'email:rfc', 'max:255'],
            'status' => ['required', Rule::enum(EventStatus::class)],
            'publishedAt' => [
                Rule::requiredIf($this->status === EventStatus::Scheduled->value),
                'nullable',
                'date',
                Rule::when($this->status === EventStatus::Scheduled->value, ['after:now']),
            ],
            'isFeatured' => ['boolean'],
            'isLivestreamed' => ['boolean'],
        ];
    }

    public function validateChronology(): void
    {
        $startsAt = $this->startsAt();
        $endsAt = $this->endsAt();
        $deadline = $this->dateTime($this->registrationDeadline);
        $errors = [];

        if ($endsAt !== null && $endsAt->lt($startsAt)) {
            $errors['form.endTime'] = __('The event end must be after the event start.');
        }

        if ($deadline !== null && $deadline->gt($startsAt)) {
            $errors['form.registrationDeadline'] = __('The registration deadline must not be after the event starts.');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed> */
    public function eventData(): array
    {
        return collect([
            'event_type_id' => $this->eventTypeId,
            'ministry_id' => $this->ministryId,
            'title' => $this->title,
            'short_description' => $this->shortDescription,
            'description' => $this->description,
            'location_type' => $this->locationType,
            'venue_name' => in_array($this->locationType, ['physical', 'hybrid'], true) ? $this->venueName : null,
            'address' => in_array($this->locationType, ['physical', 'hybrid'], true) ? $this->address : null,
            'city' => in_array($this->locationType, ['physical', 'hybrid'], true) ? $this->city : null,
            'region' => in_array($this->locationType, ['physical', 'hybrid'], true) ? $this->region : null,
            'country' => $this->country,
            'location_url' => in_array($this->locationType, ['physical', 'hybrid'], true) ? $this->locationUrl : null,
            'meeting_url' => in_array($this->locationType, ['online', 'hybrid'], true) ? $this->meetingUrl : null,
            'registration_url' => $this->registrationRequired ? $this->registrationUrl : null,
            'contact_name' => $this->contactName,
            'contact_phone' => $this->contactPhone,
            'contact_email' => $this->contactEmail,
            'starts_at' => $this->startsAt(),
            'ends_at' => $this->endsAt(),
            'timezone' => $this->timezone,
            'is_all_day' => $this->isAllDay,
            'is_recurring' => $this->isRecurring,
            'recurrence_rule' => $this->recurrenceRule(),
            'registration_required' => $this->registrationRequired,
            'registration_deadline' => $this->dateTime($this->registrationDeadline),
            'maximum_attendees' => $this->maximumAttendees,
            'is_featured' => $this->isFeatured,
            'is_livestreamed' => $this->isLivestreamed,
            'status' => $this->status,
            'published_at' => $this->dateTime($this->publishedAt),
        ])->map(fn (mixed $value): mixed => $value === '' ? null : $value)->all();
    }

    private function startsAt(): Carbon
    {
        return Carbon::parse($this->startDate.' '.($this->isAllDay ? '00:00' : $this->startTime), $this->timezone)->utc();
    }

    private function endsAt(): ?Carbon
    {
        if ($this->endDate === '') {
            return null;
        }

        return Carbon::parse($this->endDate.' '.($this->isAllDay ? '23:59:59' : $this->endTime), $this->timezone)->utc();
    }

    private function dateTime(string $value): ?Carbon
    {
        return $value === '' ? null : Carbon::parse($value, $this->timezone)->utc();
    }

    private function recurrenceRule(): ?string
    {
        if (! $this->isRecurring) {
            return null;
        }

        return $this->recurrence === EventRecurrence::Custom->value
            ? $this->customRecurrence
            : $this->recurrence;
    }
}
