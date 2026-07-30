<?php

namespace App\Livewire\Forms;

use App\MinistryStatus;
use App\Models\Event;
use App\Models\Ministry;
use App\Models\Person;
use App\Models\Sermon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class MinistryForm extends Form
{
    public ?int $ministryId = null;

    public string $name = '';

    public string $slug = '';

    public bool $slugManuallyEdited = false;

    public string $shortDescription = '';

    public string $description = '';

    public string $mission = '';

    public string $vision = '';

    public mixed $featuredImage = null;

    public mixed $logo = null;

    public bool $removeFeaturedImage = false;

    public bool $removeLogo = false;

    public string $meetingDay = '';

    public string $meetingTime = '';

    public string $meetingLocation = '';

    public string $contactEmail = '';

    public string $contactPhone = '';

    public int|string $displayOrder = 0;

    public string $status = 'draft';

    public bool $isFeatured = false;

    public string $publishedAt = '';

    /** @var array<int, array{person_id: int|string, role_title: string, is_primary: bool, display_order: int|string}> */
    public array $leaders = [];

    /** @var array<int, int|string> */
    public array $sermonIds = [];

    /** @var array<int, int|string> */
    public array $eventIds = [];

    public function setMinistry(Ministry $ministry): void
    {
        $ministry->loadMissing(['leaders:id', 'sermons:id', 'events:id']);
        $this->ministryId = $ministry->id;
        $this->name = $ministry->name;
        $this->slug = $ministry->slug;
        $this->slugManuallyEdited = true;
        $this->shortDescription = $ministry->short_description ?? '';
        $this->description = $ministry->description ?? '';
        $this->mission = $ministry->mission ?? '';
        $this->vision = $ministry->vision ?? '';
        $this->meetingDay = $ministry->meeting_day ?? '';
        $this->meetingTime = $ministry->meeting_time?->format('H:i') ?? '';
        $this->meetingLocation = $ministry->meeting_location ?? '';
        $this->contactEmail = $ministry->contact_email ?? '';
        $this->contactPhone = $ministry->contact_phone ?? '';
        $this->displayOrder = $ministry->display_order;
        $this->status = $ministry->status->value;
        $this->isFeatured = $ministry->is_featured;
        $this->publishedAt = $ministry->published_at?->format('Y-m-d\TH:i') ?? '';
        $this->leaders = array_values($ministry->leaders->map(fn (Person $person): array => [
            'person_id' => $person->id,
            'role_title' => (string) ($person->pivot?->getAttribute('role_title') ?? ''),
            'is_primary' => (bool) $person->pivot?->getAttribute('is_primary'),
            'display_order' => (int) $person->pivot?->getAttribute('display_order'),
        ])->values()->all());
        $this->sermonIds = array_values($ministry->sermons->modelKeys());
        $this->eventIds = array_values($ministry->events->modelKeys());
    }

    public function updatedName(string $name): void
    {
        if (! $this->slugManuallyEdited && $this->ministryId === null) {
            $this->slug = Str::slug($name);
        }
    }

    public function markSlugAsEdited(): void
    {
        $this->slugManuallyEdited = true;
    }

    public function addLeader(): void
    {
        $this->leaders[] = ['person_id' => '', 'role_title' => '', 'is_primary' => false, 'display_order' => count($this->leaders)];
    }

    public function removeLeader(int $index): void
    {
        unset($this->leaders[$index]);
        $this->leaders = array_values($this->leaders);
    }

    public function normalize(): void
    {
        foreach (['name', 'shortDescription', 'mission', 'vision', 'meetingDay', 'meetingLocation', 'contactEmail', 'contactPhone'] as $property) {
            $this->{$property} = Str::squish($this->{$property});
        }
        $this->slug = Str::slug($this->slug !== '' ? $this->slug : $this->name);
        $this->displayOrder = (int) $this->displayOrder;
        $this->leaders = array_values(collect($this->leaders)->map(fn (array $leader): array => [
            'person_id' => (int) $leader['person_id'],
            'role_title' => Str::squish((string) $leader['role_title']),
            'is_primary' => (bool) $leader['is_primary'],
            'display_order' => (int) $leader['display_order'],
        ])->values()->all());
        $this->sermonIds = array_values(collect($this->sermonIds)->map(fn ($id): int => (int) $id)->unique()->values()->all());
        $this->eventIds = array_values(collect($this->eventIds)->map(fn ($id): int => (int) $id)->unique()->values()->all());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:255', Rule::unique(Ministry::class, 'slug')->ignore($this->ministryId)],
            'shortDescription' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:100000'],
            'mission' => ['nullable', 'string', 'max:5000'],
            'vision' => ['nullable', 'string', 'max:5000'],
            'featuredImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'removeFeaturedImage' => ['boolean'],
            'removeLogo' => ['boolean'],
            'meetingDay' => ['nullable', 'string', 'max:50'],
            'meetingTime' => ['nullable', 'date_format:H:i'],
            'meetingLocation' => ['nullable', 'string', 'max:255'],
            'contactEmail' => ['nullable', 'email:rfc', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:64', 'regex:/^[0-9+().\s-]*$/'],
            'displayOrder' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'status' => ['required', Rule::enum(MinistryStatus::class)],
            'isFeatured' => ['boolean'],
            'publishedAt' => ['nullable', 'date'],
            'leaders' => ['array', 'max:50'],
            'leaders.*.person_id' => ['required', 'integer', 'distinct', Rule::exists(Person::class, 'id')],
            'leaders.*.role_title' => ['nullable', 'string', 'max:150'],
            'leaders.*.is_primary' => ['boolean'],
            'leaders.*.display_order' => ['required', 'integer', 'min:0'],
            'sermonIds' => ['array', 'max:100'],
            'sermonIds.*' => ['integer', 'distinct', Rule::exists(Sermon::class, 'id')->withoutTrashed()],
            'eventIds' => ['array', 'max:100'],
            'eventIds.*' => ['integer', 'distinct', Rule::exists(Event::class, 'id')->withoutTrashed()],
        ];
    }

    public function validateLeadership(): void
    {
        if (collect($this->leaders)->where('is_primary', true)->count() > 1) {
            throw ValidationException::withMessages(['form.leaders' => __('Only one ministry leader may be marked as primary.')]);
        }
    }

    /** @return array<string, mixed> */
    public function ministryData(): array
    {
        return collect([
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->shortDescription,
            'description' => trim($this->description),
            'mission' => $this->mission,
            'vision' => $this->vision,
            'meeting_day' => $this->meetingDay,
            'meeting_time' => $this->meetingTime,
            'meeting_location' => $this->meetingLocation,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'display_order' => $this->displayOrder,
            'status' => $this->status,
            'is_featured' => $this->isFeatured,
            'published_at' => $this->publishedAt,
        ])->map(fn (mixed $value): mixed => $value === '' ? null : $value)->all();
    }
}
