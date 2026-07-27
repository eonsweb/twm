<?php

namespace App\Livewire\Forms;

use App\Models\LeadershipPosition;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class LeadershipPersonForm extends Form
{
    public ?int $personId = null;

    public ?int $userId = null;

    public string $title = '';

    public string $firstName = '';

    public string $middleName = '';

    public string $lastName = '';

    public string $slug = '';

    public mixed $portrait = null;

    public string $shortBio = '';

    public string $biography = '';

    public string $email = '';

    public string $phone = '';

    public string $websiteUrl = '';

    public string $facebookUrl = '';

    public string $instagramUrl = '';

    public string $youtubeUrl = '';

    /** @var list<int|string> */
    public array $positionIds = [];

    public int|string|null $primaryPositionId = null;

    public string $displayTitle = '';

    public string $startedAt = '';

    public string $endedAt = '';

    public bool $isCurrent = true;

    public int $sortOrder = 0;

    public bool $isActive = true;

    public bool $isPublic = true;

    public function setPerson(Person $person): void
    {
        $person->loadMissing('leadershipAssignments');
        $primaryAssignment = $person->leadershipAssignments->firstWhere('is_primary', true)
            ?? $person->leadershipAssignments->first();

        $this->personId = $person->id;
        $this->userId = $person->user_id;
        $this->title = $person->title ?? '';
        $this->firstName = $person->first_name;
        $this->middleName = $person->middle_name ?? '';
        $this->lastName = $person->last_name;
        $this->slug = $person->slug;
        $this->shortBio = $person->short_bio ?? '';
        $this->biography = $person->biography ?? '';
        $this->email = $person->email ?? '';
        $this->phone = $person->phone ?? '';
        $this->websiteUrl = $person->website_url ?? '';
        $this->facebookUrl = $person->facebook_url ?? '';
        $this->instagramUrl = $person->instagram_url ?? '';
        $this->youtubeUrl = $person->youtube_url ?? '';
        $positionIds = $person->leadershipAssignments
            ->pluck('leadership_position_id')
            ->map(fn (int $positionId): int => $positionId)
            ->values()
            ->all();
        $this->positionIds = array_values($positionIds);

        if ($primaryAssignment !== null) {
            $this->primaryPositionId = $primaryAssignment->leadership_position_id;
            $this->displayTitle = $primaryAssignment->display_title ?? '';
            $this->startedAt = $primaryAssignment->started_at?->toDateString() ?? '';
            $this->endedAt = $primaryAssignment->ended_at?->toDateString() ?? '';
            $this->isCurrent = $primaryAssignment->is_current;
            $this->sortOrder = $primaryAssignment->sort_order;
        }

        $this->isActive = $person->is_active;
        $this->isPublic = $person->is_public;
    }

    public function normalize(): void
    {
        $this->slug = Str::slug($this->slug !== '' ? $this->slug : $this->nameWithoutTitle());
        $positionIds = collect($this->positionIds)
            ->map(fn (int|string $positionId): int => (int) $positionId)
            ->unique()
            ->values()
            ->all();
        $this->positionIds = array_values($positionIds);
        $this->primaryPositionId = $this->primaryPositionId !== null
            ? (int) $this->primaryPositionId
            : null;

        foreach (['websiteUrl', 'facebookUrl', 'instagramUrl', 'youtubeUrl'] as $property) {
            $this->{$property} = $this->normalizeUrl($this->{$property});
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'userId' => [
                'nullable',
                'integer',
                Rule::exists(User::class, 'id'),
                Rule::unique(Person::class, 'user_id')->ignore($this->personId),
            ],
            'title' => ['nullable', 'string', 'max:100'],
            'firstName' => ['required', 'string', 'max:255'],
            'middleName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique(Person::class, 'slug')->ignore($this->personId),
            ],
            'portrait' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
            ],
            'shortBio' => ['nullable', 'string', 'max:1000'],
            'biography' => ['nullable', 'string', 'max:50000'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'websiteUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'facebookUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'instagramUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'youtubeUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'positionIds' => ['required', 'array', 'min:1'],
            'positionIds.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(LeadershipPosition::class, 'id'),
            ],
            'primaryPositionId' => [
                'required',
                'integer',
                Rule::in($this->positionIds),
            ],
            'displayTitle' => ['nullable', 'string', 'max:255'],
            'startedAt' => ['nullable', 'date'],
            'endedAt' => ['nullable', 'date', 'after_or_equal:startedAt'],
            'isCurrent' => ['boolean'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'isActive' => ['boolean'],
            'isPublic' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function personData(): array
    {
        $data = [
            'user_id' => $this->userId,
            'title' => $this->title,
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'slug' => $this->slug,
            'short_bio' => $this->shortBio,
            'biography' => $this->biography,
            'email' => $this->email,
            'phone' => $this->phone,
            'website_url' => $this->websiteUrl,
            'facebook_url' => $this->facebookUrl,
            'instagram_url' => $this->instagramUrl,
            'youtube_url' => $this->youtubeUrl,
            'is_active' => $this->isActive,
            'is_public' => $this->isPublic,
        ];

        return collect($data)
            ->map(fn (mixed $value): mixed => $value === '' ? null : $value)
            ->all();
    }

    /**
     * @return array{
     *     position_ids: list<int>,
     *     primary_position_id: int,
     *     display_title: string|null,
     *     started_at: string|null,
     *     ended_at: string|null,
     *     is_current: bool,
     *     sort_order: int
     * }
     */
    public function assignmentData(): array
    {
        return [
            'position_ids' => array_map('intval', $this->positionIds),
            'primary_position_id' => (int) $this->primaryPositionId,
            'display_title' => $this->displayTitle ?: null,
            'started_at' => $this->startedAt ?: null,
            'ended_at' => $this->endedAt ?: null,
            'is_current' => $this->isCurrent,
            'sort_order' => $this->sortOrder,
        ];
    }

    private function nameWithoutTitle(): string
    {
        return collect([$this->firstName, $this->middleName, $this->lastName])
            ->filter()
            ->implode(' ');
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return 'https://'.$url;
    }
}
