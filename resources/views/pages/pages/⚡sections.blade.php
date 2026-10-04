<?php

use App\Activity\ActivityLogger;
use App\Blog\HtmlSanitizer;
use App\MediaStatus;
use App\MediaType;
use App\MediaVisibility;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageSection;
use App\PageSectionType;
use App\Pages\HomepageHero;
use App\Pages\HomepageSectionSynchronizer;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Page $page;
    public ?int $sectionId = null;
    public string $sectionType = 'rich-text';
    public string $name = '';
    public string $heading = '';
    public string $subheading = '';
    public string $content = '';
    public string $settings = '{}';
    public bool $isVisible = true;

    /** @var list<int> */
    public array $backgroundMediaIds = [];

    public string $backgroundAlt = '';
    public string $welcomeSignature = '';
    public string $welcomePastorName = '';
    public string $welcomePastorRole = '';
    public string $heroVariant = 'anniversary';
    public string $heroAnniversaryNumber = '20';
    public string $heroAnniversaryUnit = 'YEARS';
    public string $heroScriptHeading = '';
    public string $heroTheme = '';
    public string $heroPrimaryLabel = '';
    public string $heroPrimaryUrl = '';
    public string $heroSecondaryLabel = '';
    public string $heroSecondaryUrl = '';

    /** @var list<int> */
    public array $heroEmblemMediaIds = [];

    public function mount(Page $page, HomepageSectionSynchronizer $homepageSections): void
    {
        Gate::authorize('manageSections', $page);
        $homepageSections->sync($page);
        $this->page = $page->refresh()->load(['sections.backgroundImage']);
    }

    #[Computed]
    public function selectedBackgroundMedia(): ?Media
    {
        if ($this->backgroundMediaIds === []) {
            return null;
        }

        return Media::query()
            ->whereKey($this->backgroundMediaIds[0])
            ->first(['id', 'name', 'path', 'disk', 'media_type', 'visibility', 'status', 'alt_text', 'width', 'height']);
    }

    #[Computed]
    public function selectedHeroEmblemMedia(): ?Media
    {
        if ($this->heroEmblemMediaIds === []) {
            return null;
        }

        return Media::query()
            ->whereKey($this->heroEmblemMediaIds[0])
            ->first(['id', 'name', 'path', 'disk', 'media_type', 'visibility', 'status', 'alt_text', 'width', 'height']);
    }

    public function edit(int $id, HomepageHero $homepageHero): void
    {
        Gate::authorize('manageSections', $this->page);
        $section = $this->page->sections()->findOrFail($id);
        $hero = $section->section_type === PageSectionType::Hero
            ? $homepageHero->resolve($section)['settings']
            : null;

        $this->sectionId = $section->id;
        $this->sectionType = $section->section_type->value;
        $this->name = $section->name;
        $this->heading = (string) ($hero['heading'] ?? $section->heading ?? '');
        $this->subheading = (string) ($hero['eyebrow'] ?? $section->subheading ?? '');
        $this->content = (string) ($hero['description'] ?? $section->content ?? '');
        $excludedSettings = [
            'background_alt',
            'pastor_image_alt',
            'signature_text',
            'pastor_name',
            'pastor_title',
            'welcome_signature',
            'welcome_pastor_name',
            'welcome_pastor_role',
            ...($hero === null ? [] : $homepageHero->editableSettingKeys()),
        ];
        $this->settings = json_encode(
            collect($section->settings ?? [])->except($excludedSettings)->all(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
        $this->isVisible = $section->is_visible;
        $this->backgroundMediaIds = $section->background_image_id === null ? [] : [$section->background_image_id];
        $this->backgroundAlt = (string) data_get(
            $section->settings,
            in_array($section->section_type, [PageSectionType::Welcome, PageSectionType::WelcomeUpcomingEvent], true)
                ? 'pastor_image_alt'
                : 'background_alt',
            '',
        );
        $this->welcomeSignature = (string) (data_get($section->settings, 'welcome_signature') ?: data_get($section->settings, 'signature_text', ''));
        $this->welcomePastorName = (string) (data_get($section->settings, 'welcome_pastor_name') ?: data_get($section->settings, 'pastor_name', ''));
        $this->welcomePastorRole = (string) (data_get($section->settings, 'welcome_pastor_role') ?: data_get($section->settings, 'pastor_title', ''));
        $this->heroVariant = (string) ($hero['variant'] ?? 'anniversary');
        $this->heroAnniversaryNumber = (string) ($hero['anniversary_number'] ?? '20');
        $this->heroAnniversaryUnit = (string) ($hero['anniversary_unit'] ?? 'YEARS');
        $this->heroScriptHeading = (string) ($hero['script_heading'] ?? '');
        $this->heroTheme = (string) ($hero['theme'] ?? '');
        $this->heroPrimaryLabel = (string) ($hero['primary_label'] ?? '');
        $this->heroPrimaryUrl = (string) ($hero['primary_url'] ?? '');
        $this->heroSecondaryLabel = (string) ($hero['secondary_label'] ?? '');
        $this->heroSecondaryUrl = (string) ($hero['secondary_url'] ?? '');
        $this->heroEmblemMediaIds = filled($hero['emblem_media_id'] ?? null) ? [(int) $hero['emblem_media_id']] : [];
        unset($this->selectedBackgroundMedia);
        unset($this->selectedHeroEmblemMedia);
    }

    public function save(HtmlSanitizer $sanitizer, ActivityLogger $logger): void
    {
        Gate::authorize('manageSections', $this->page);

        $validated = $this->validate([
            'sectionType' => ['required', Rule::enum(PageSectionType::class)],
            'name' => ['required', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'subheading' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:200000'],
            'settings' => ['required', 'json'],
            'isVisible' => ['boolean'],
            'backgroundMediaIds' => ['array', 'max:1'],
            'backgroundMediaIds.*' => ['integer', 'distinct', Rule::exists(Media::class, 'id')->withoutTrashed()],
            'backgroundAlt' => ['nullable', 'string', 'max:255'],
            'welcomeSignature' => ['nullable', 'string', 'max:255'],
            'welcomePastorName' => ['nullable', 'string', 'max:255'],
            'welcomePastorRole' => ['nullable', 'string', 'max:255'],
            'heroVariant' => ['required', Rule::in(['default', 'anniversary'])],
            'heroAnniversaryNumber' => ['nullable', 'string', 'max:20'],
            'heroAnniversaryUnit' => ['nullable', 'string', 'max:30'],
            'heroScriptHeading' => ['nullable', 'string', 'max:100'],
            'heroTheme' => ['nullable', 'string', 'max:500'],
            'heroPrimaryLabel' => ['nullable', 'string', 'max:100'],
            'heroPrimaryUrl' => ['nullable', 'string', 'max:2048'],
            'heroSecondaryLabel' => ['nullable', 'string', 'max:100'],
            'heroSecondaryUrl' => ['nullable', 'string', 'max:2048'],
            'heroEmblemMediaIds' => ['array', 'max:1'],
            'heroEmblemMediaIds.*' => ['integer', 'distinct', Rule::exists(Media::class, 'id')->withoutTrashed()],
        ]);

        if ($this->page->is_homepage
            && $validated['sectionType'] === PageSectionType::Welcome->value
            && $this->page->sections()
                ->where('section_type', PageSectionType::Welcome->value)
                ->when($this->sectionId !== null, fn ($query) => $query->whereKeyNot($this->sectionId))
                ->exists()) {
            throw ValidationException::withMessages([
                'sectionType' => __('The homepage already has a Welcome Section.'),
            ]);
        }

        $usesSectionImage = in_array($validated['sectionType'], [
            PageSectionType::Hero->value,
            PageSectionType::Welcome->value,
            PageSectionType::WelcomeUpcomingEvent->value,
        ], true);
        $backgroundMedia = $usesSectionImage
            ? $this->validatedBackgroundMedia()
            : null;
        $settings = $this->validatedSettings($validated['sectionType'], $validated['settings']);

        if ($validated['sectionType'] === PageSectionType::Hero->value) {
            $emblemMedia = $this->validatedHeroEmblemMedia();
            $settings = [
                ...$settings,
                ...array_filter([
                    'variant' => $validated['heroVariant'],
                    'anniversary_number' => trim($validated['heroAnniversaryNumber']),
                    'anniversary_unit' => trim($validated['heroAnniversaryUnit']),
                    'script_heading' => trim($validated['heroScriptHeading']),
                    'theme' => trim($validated['heroTheme']),
                    'emblem_media_id' => $emblemMedia?->id,
                    'primary_label' => trim($validated['heroPrimaryLabel']),
                    'primary_url' => trim($validated['heroPrimaryUrl']),
                    'secondary_label' => trim($validated['heroSecondaryLabel']),
                    'secondary_url' => trim($validated['heroSecondaryUrl']),
                ], fn (mixed $value): bool => filled($value)),
            ];
        }

        if (in_array($validated['sectionType'], [PageSectionType::Welcome->value, PageSectionType::WelcomeUpcomingEvent->value], true)) {
            $settings = array_filter([
                ...$settings,
                'welcome_signature' => trim($validated['welcomeSignature']),
                'welcome_pastor_name' => trim($validated['welcomePastorName']),
                'welcome_pastor_role' => trim($validated['welcomePastorRole']),
            ], fn (mixed $value): bool => filled($value));
        }

        if ($usesSectionImage && filled($validated['backgroundAlt'])) {
            $imageAltKey = $validated['sectionType'] === PageSectionType::Hero->value
                ? 'background_alt'
                : 'pastor_image_alt';
            $settings[$imageAltKey] = trim($validated['backgroundAlt']);
        }

        $section = $this->sectionId
            ? $this->page->sections()->findOrFail($this->sectionId)
            : new PageSection([
                'page_id' => $this->page->id,
                'sort_order' => (int) $this->page->sections()->max('sort_order') + 1,
                'created_by' => auth()->id(),
            ]);
        $event = $section->exists ? 'updated' : 'added';

        $section->fill([
            'section_type' => $validated['sectionType'],
            'name' => $validated['name'],
            'heading' => $validated['heading'],
            'subheading' => $validated['subheading'],
            'content' => $sanitizer->sanitize($validated['content']),
            'settings' => $settings,
            'is_visible' => $validated['isVisible'],
            'background_image_id' => $backgroundMedia?->id,
            'updated_by' => auth()->id(),
        ])->save();

        $logger->log(
            'pages',
            'page-section.'.$event,
            str($event)->headline().' section "'.$section->name.'".',
            $this->page,
            auth()->user(),
            properties: ['section_id' => $section->id],
        );

        $this->resetForm();
        $this->page->load('sections.backgroundImage');
        Flux::toast(variant: 'success', text: __('Section settings saved.'));
    }

    public function toggle(int $id, ActivityLogger $logger): void
    {
        Gate::authorize('manageSections', $this->page);
        $section = $this->page->sections()->findOrFail($id);
        $section->update(['is_visible' => ! $section->is_visible, 'updated_by' => auth()->id()]);
        $logger->log('pages', 'page-section.toggled', 'Changed section visibility.', $this->page, auth()->user(), properties: ['section_id' => $section->id, 'is_visible' => $section->is_visible]);
        $this->page->load('sections.backgroundImage');
    }

    public function move(int $id, string $direction, ActivityLogger $logger): void
    {
        Gate::authorize('manageSections', $this->page);
        abort_unless(in_array($direction, ['up', 'down'], true), 422);

        $moved = DB::transaction(function () use ($id, $direction): bool {
            $sections = $this->page->sections()
                ->reorder()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $sectionIndex = $sections->search(fn (PageSection $section): bool => $section->id === $id);

            abort_if($sectionIndex === false, 404);

            $otherIndex = $direction === 'up' ? $sectionIndex - 1 : $sectionIndex + 1;
            $other = $sections->get($otherIndex);

            if (! $other instanceof PageSection) {
                return false;
            }

            if ($sections->pluck('sort_order')->duplicates()->isNotEmpty()) {
                $sections->each(function (PageSection $section, int $index): void {
                    $section->sort_order = ($index + 1) * 10;
                    $section->saveQuietly();
                });
            }

            $section = $sections->get($sectionIndex);
            $sectionOrder = $section->sort_order;
            $otherOrder = $other->sort_order;
            $temporaryOrder = (int) $sections->max('sort_order') + 1;

            $section->sort_order = $temporaryOrder;
            $section->saveQuietly();
            $other->sort_order = $sectionOrder;
            $other->saveQuietly();
            $section->sort_order = $otherOrder;
            $section->saveQuietly();

            return true;
        }, attempts: 3);

        if (! $moved) {
            return;
        }

        $logger->log('pages', 'page-section.reordered', 'Reordered page sections.', $this->page, auth()->user());
        $this->page->load('sections.backgroundImage');
    }

    public function duplicate(int $id): void
    {
        Gate::authorize('manageSections', $this->page);
        abort_if($this->page->is_homepage, 404);

        $section = $this->page->sections()->findOrFail($id);
        $copy = $section->replicate();
        $copy->name = $section->name.' (Copy)';
        $copy->sort_order = (int) $this->page->sections()->max('sort_order') + 1;
        $copy->created_by = auth()->id();
        $copy->updated_by = auth()->id();
        $copy->save();
        $this->page->load('sections.backgroundImage');
    }

    public function delete(int $id): void
    {
        Gate::authorize('manageSections', $this->page);
        $this->page->sections()->findOrFail($id)->delete();
        $this->page->load('sections.backgroundImage');
    }

    public function resetForm(): void
    {
        $this->reset(
            'sectionId',
            'name',
            'heading',
            'subheading',
            'content',
            'backgroundMediaIds',
            'backgroundAlt',
            'welcomeSignature',
            'welcomePastorName',
            'welcomePastorRole',
            'heroVariant',
            'heroAnniversaryNumber',
            'heroAnniversaryUnit',
            'heroScriptHeading',
            'heroTheme',
            'heroPrimaryLabel',
            'heroPrimaryUrl',
            'heroSecondaryLabel',
            'heroSecondaryUrl',
            'heroEmblemMediaIds',
        );
        $this->sectionType = 'rich-text';
        $this->settings = '{}';
        $this->isVisible = true;
        unset($this->selectedBackgroundMedia);
        unset($this->selectedHeroEmblemMedia);
    }

    private function validatedBackgroundMedia(): ?Media
    {
        if ($this->backgroundMediaIds === []) {
            return null;
        }

        $media = Media::query()->find($this->backgroundMediaIds[0]);

        if (! $media) {
            throw ValidationException::withMessages([
                'backgroundMediaIds' => __('The selected background image no longer exists.'),
            ]);
        }

        Gate::authorize('view', $media);

        if ($media->media_type !== MediaType::Image
            || $media->visibility !== MediaVisibility::Public
            || $media->status !== MediaStatus::Active
            || ! $media->existsOnDisk()) {
            throw ValidationException::withMessages([
                'backgroundMediaIds' => __('The background must be an available, active, public image from the Media Library.'),
            ]);
        }

        return $media;
    }

    private function validatedHeroEmblemMedia(): ?Media
    {
        if ($this->heroEmblemMediaIds === []) {
            return null;
        }

        $media = Media::query()->find($this->heroEmblemMediaIds[0]);

        if (! $media) {
            throw ValidationException::withMessages([
                'heroEmblemMediaIds' => __('The selected anniversary emblem no longer exists.'),
            ]);
        }

        Gate::authorize('view', $media);

        if ($media->media_type !== MediaType::Image
            || $media->visibility !== MediaVisibility::Public
            || $media->status !== MediaStatus::Active
            || ! $media->existsOnDisk()) {
            throw ValidationException::withMessages([
                'heroEmblemMediaIds' => __('The anniversary emblem must be an available, active, public image from the Media Library.'),
            ]);
        }

        return $media;
    }

    /** @return array<string, mixed> */
    private function validatedSettings(string $type, string $json): array
    {
        $settings = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $rules = match ($type) {
            'hero' => [
                'variant' => ['nullable', Rule::in(['default', 'anniversary'])],
                'anniversary_number' => ['nullable', 'string', 'max:20'],
                'anniversary_unit' => ['nullable', 'string', 'max:30'],
                'primary_label' => ['nullable', 'string', 'max:100'],
                'primary_url' => ['nullable', 'string', 'max:2048'],
                'secondary_label' => ['nullable', 'string', 'max:100'],
                'secondary_url' => ['nullable', 'string', 'max:2048'],
                'eyebrow' => ['nullable', 'string', 'max:150'],
                'script_heading' => ['nullable', 'string', 'max:100'],
                'theme' => ['nullable', 'string', 'max:500'],
                'emblem_media_id' => ['nullable', 'integer'],
                'show_emblem' => ['nullable', 'boolean'],
                'show_theme' => ['nullable', 'boolean'],
                'show_description' => ['nullable', 'boolean'],
                'show_primary_cta' => ['nullable', 'boolean'],
                'show_secondary_cta' => ['nullable', 'boolean'],
                'show_scroll_indicator' => ['nullable', 'boolean'],
                'overlay' => ['nullable', 'integer', 'between:0,100'],
                'alignment' => ['nullable', Rule::in(['left', 'center', 'right'])],
            ],
            'featured-sermons', 'latest-posts', 'ministries-grid', 'leadership-grid', 'books-grid' => [
                'limit' => ['nullable', 'integer', 'between:1,24'],
                'sort' => ['nullable', Rule::in(['latest', 'oldest', 'featured', 'manual'])],
                'category_id' => ['nullable', 'integer'],
                'speaker_id' => ['nullable', 'integer'],
                'show_view_all' => ['nullable', 'boolean'],
            ],
            'upcoming-events' => [
                'event_type_id' => ['nullable', 'integer', Rule::exists(\App\Models\EventType::class, 'id')],
                'limit' => ['nullable', 'integer', 'between:1,24'],
                'display_style' => ['nullable', Rule::in(['cards', 'weekly-events'])],
                'show_read_more' => ['nullable', 'boolean'],
                'show_icon' => ['nullable', 'boolean'],
                'show_time' => ['nullable', 'boolean'],
                'show_day' => ['nullable', 'boolean'],
                'view_all_label' => ['nullable', 'string', 'max:100'],
            ],
            'welcome', 'welcome-upcoming-event' => [
                'signature_text' => ['nullable', 'string', 'max:255'],
                'pastor_name' => ['nullable', 'string', 'max:255'],
                'pastor_title' => ['nullable', 'string', 'max:255'],
            ],
            'call-to-action', 'donation-callout', 'prayer-request-callout' => [
                'button_label' => ['nullable', 'string', 'max:100'],
                'url' => ['nullable', 'string', 'max:2048'],
            ],
            default => [],
        };
        $unknown = array_diff(array_keys($settings), array_keys($rules));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'settings' => __('Unsupported setting: :setting', ['setting' => reset($unknown)]),
            ]);
        }

        return Validator::make($settings, $rules)->validate();
    }
};
?>

@php
    $isWelcomeSectionType = in_array($sectionType, [PageSectionType::Welcome->value, PageSectionType::WelcomeUpcomingEvent->value], true);
    $isHeroSectionType = $sectionType === PageSectionType::Hero->value;
@endphp

<main id="admin-main" class="p-4 sm:p-6 lg:p-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <x-admin.page-header :title="__('Sections: :page', ['page' => $page->title])" :description="$page->is_homepage ? __('Configure, show, hide, and reorder the predefined homepage regions.') : __('Add, configure, hide, duplicate, and reorder approved section types.')">
            <x-slot:actions>
                <flux:button :href="route('pages.edit', $page)" wire:navigate>{{ __('Back to page') }}</flux:button>
            </x-slot:actions>
        </x-admin.page-header>

        @if($page->is_homepage)
            <livewire:pages::pages.hero-slides :page="$page" :key="'hero-slides-'.$page->id" />
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(22rem,1fr)]">
            <div class="space-y-3">
                @forelse($page->sections as $section)
                    <flux:card wire:key="section-{{ $section->id }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading>{{ $section->name }}</flux:heading>
                                <flux:badge size="sm">{{ $section->section_type->label() }}</flux:badge>
                                @unless($section->is_visible)<flux:badge size="sm" color="zinc">{{ __('Hidden') }}</flux:badge>@endunless
                            </div>
                            <p class="mt-1 truncate text-sm text-slate-500">{{ $section->heading }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if($page->is_homepage && $section->section_type === PageSectionType::Hero)
                                <flux:button size="sm" href="#homepage-hero-slides">{{ __('Manage slides') }}</flux:button>
                            @endif
                            @if($loop->first)
                                <flux:button size="sm" icon="arrow-up" disabled class="disabled:cursor-not-allowed disabled:opacity-50" :tooltip="__('Move section up')" :aria-label="__('Move :name up', ['name' => $section->name])" />
                            @else
                                <flux:button size="sm" icon="arrow-up" wire:click="move({{ $section->id }}, 'up')" :tooltip="__('Move section up')" :aria-label="__('Move :name up', ['name' => $section->name])" />
                            @endif
                            @if($loop->last)
                                <flux:button size="sm" icon="arrow-down" disabled class="disabled:cursor-not-allowed disabled:opacity-50" :tooltip="__('Move section down')" :aria-label="__('Move :name down', ['name' => $section->name])" />
                            @else
                                <flux:button size="sm" icon="arrow-down" wire:click="move({{ $section->id }}, 'down')" :tooltip="__('Move section down')" :aria-label="__('Move :name down', ['name' => $section->name])" />
                            @endif
                            <flux:button size="sm" :icon="$section->is_visible ? 'eye-slash' : 'eye'" wire:click="toggle({{ $section->id }})" :tooltip="$section->is_visible ? __('Hide section') : __('Show section')" :aria-label="$section->is_visible ? __('Hide section') : __('Show section')" />
                            @unless($page->is_homepage)
                                <flux:button size="sm" wire:click="duplicate({{ $section->id }})" icon="document-duplicate" :tooltip="__('Duplicate section')" :aria-label="__('Duplicate section')" />
                            @endunless
                            <flux:button size="sm" wire:click="edit({{ $section->id }})" icon="pencil" :tooltip="__('Edit section')" :aria-label="__('Edit :name', ['name' => $section->name])" />
                            <flux:button size="sm" variant="danger" wire:click="delete({{ $section->id }})" wire:confirm="{{ __('Delete this section?') }}" icon="trash" :tooltip="__('Delete section')" :aria-label="__('Delete :name', ['name' => $section->name])" />
                        </div>
                    </flux:card>
                @empty
                    <x-admin.empty-state icon="rectangle-stack" :title="__('No sections yet')" :description="__('Add the first reusable section using the form.')" />
                @endforelse
            </div>

            <form wire:submit="save">
                <flux:card class="space-y-5">
                    <flux:heading size="lg">{{ $sectionId ? __('Edit section') : __('Add section') }}</flux:heading>
                    <flux:select wire:model.live="sectionType" :label="__('Section type')">
                        @foreach(PageSectionType::cases() as $type)
                            @continue($page->is_homepage && $type === PageSectionType::WelcomeUpcomingEvent)
                            @continue($page->is_homepage && $type === PageSectionType::Welcome && $sectionId === null && $page->sections->contains(fn (PageSection $section): bool => $section->section_type === PageSectionType::Welcome))
                            @continue($page->is_homepage && $type === PageSectionType::FeaturedBook && $sectionId === null && $page->sections->contains(fn (PageSection $section): bool => $section->section_type === PageSectionType::FeaturedBook))
                            <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="name" :label="__('Internal name')" required />
                    <flux:input wire:model="heading" :label="$isWelcomeSectionType ? __('Welcome Header') : ($isHeroSectionType ? __('Main heading') : __('Heading'))" />
                    @unless($isWelcomeSectionType)
                        <flux:input wire:model="subheading" :label="$isHeroSectionType ? __('Celebration eyebrow') : __('Subheading')" />
                    @endunless
                    <flux:textarea wire:model="content" :label="$isWelcomeSectionType ? __('Welcome Message') : ($isHeroSectionType ? __('Description') : __('Content'))" rows="6" />

                    @if($isHeroSectionType)
                        @if($page->is_homepage)
                            <flux:text>{{ __('These settings are the fallback shown when no hero slides are eligible. Use Hero slides above to manage carousel content.') }}</flux:text>
                        @endif
                        <div class="space-y-4 rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                            <div>
                                <flux:heading>{{ __('Hero design') }}</flux:heading>
                                <flux:text class="mt-1">{{ __('Configure the public homepage hero without editing JSON.') }}</flux:text>
                            </div>

                            <flux:select wire:model.live="heroVariant" :label="__('Variant')">
                                <flux:select.option value="default">{{ __('Default') }}</flux:select.option>
                                <flux:select.option value="anniversary">{{ __('20th Anniversary') }}</flux:select.option>
                            </flux:select>

                            @if($heroVariant === 'anniversary')
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <flux:input wire:model="heroAnniversaryNumber" :label="__('Anniversary number')" maxlength="20" />
                                    <flux:input wire:model="heroAnniversaryUnit" :label="__('Anniversary unit')" maxlength="30" />
                                </div>
                                <flux:input wire:model="heroScriptHeading" :label="__('Script heading')" maxlength="100" />
                                <flux:textarea wire:model="heroTheme" :label="__('Anniversary theme')" rows="3" maxlength="500" />

                                <div class="space-y-3">
                                    <div>
                                        <flux:heading size="sm">{{ __('Anniversary emblem') }}</flux:heading>
                                        <flux:text class="mt-1">{{ __('Choose one active, public image from the Media Library.') }}</flux:text>
                                    </div>
                                    <livewire:media-picker
                                        wire:model="heroEmblemMediaIds"
                                        :allowed-types="[MediaType::Image->value]"
                                        :multiple="false"
                                        :maximum="1"
                                        collection="homepage-anniversary-emblem"
                                        :allow-upload="false"
                                        wire:key="homepage-anniversary-emblem-picker"
                                    />
                                    <flux:error name="heroEmblemMediaIds" />
                                    <flux:error name="heroEmblemMediaIds.*" />

                                    <div class="grid min-h-40 place-items-center overflow-hidden rounded-xl bg-slate-100 p-4 dark:bg-zinc-800">
                                        @if($this->selectedHeroEmblemMedia?->publicImageUrl())
                                            <img src="{{ $this->selectedHeroEmblemMedia->publicImageUrl() }}" alt="" class="max-h-40 max-w-full object-contain">
                                        @else
                                            <p class="text-center text-sm text-slate-500 dark:text-zinc-400">{{ __('No anniversary emblem selected.') }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input wire:model="heroPrimaryLabel" :label="__('Primary CTA label')" maxlength="100" />
                                <flux:input wire:model="heroPrimaryUrl" :label="__('Primary CTA URL')" maxlength="2048" />
                                <flux:input wire:model="heroSecondaryLabel" :label="__('Secondary CTA label')" maxlength="100" />
                                <flux:input wire:model="heroSecondaryUrl" :label="__('Secondary CTA URL')" maxlength="2048" />
                            </div>
                        </div>
                    @endif

                    @if($isWelcomeSectionType)
                        <flux:input wire:model="welcomeSignature" :label="__('Signature')" maxlength="255" />
                        <flux:input wire:model="welcomePastorName" label="Pastor's Name" maxlength="255" />
                        <flux:input wire:model="welcomePastorRole" label="Pastor's Role" maxlength="255" />
                    @endif

                    @if(in_array($sectionType, [PageSectionType::Hero->value, PageSectionType::Welcome->value, PageSectionType::WelcomeUpcomingEvent->value], true))
                        <div class="space-y-4 rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                            <div>
                                <flux:heading>{{ $sectionType === PageSectionType::Hero->value ? __('Hero background image') : __('Pastor image') }}</flux:heading>
                                <flux:text class="mt-1">{{ __('Choose one active, public image from the Media Library.') }}</flux:text>
                            </div>

                            <livewire:media-picker
                                wire:model="backgroundMediaIds"
                                :allowed-types="[MediaType::Image->value]"
                                :multiple="false"
                                :maximum="1"
                                collection="homepage-section"
                                :allow-upload="false"
                            />
                            <flux:error name="backgroundMediaIds" />
                            <flux:error name="backgroundMediaIds.*" />

                            <flux:input wire:model="backgroundAlt" :label="$sectionType === PageSectionType::Hero->value ? __('Background image alt text') : __('Pastor image alt text')" :description="__('Leave blank to use the Media Library alt text or title.')" maxlength="255" />

                            <div class="overflow-hidden rounded-xl bg-[rgb(36,11,54)]">
                                <div @class(['relative', 'aspect-[16/7]' => $sectionType === PageSectionType::Hero->value, 'mx-auto aspect-[4/5] max-w-56' => $isWelcomeSectionType])>
                                    @if($this->selectedBackgroundMedia?->publicImageUrl())
                                        <img src="{{ $this->selectedBackgroundMedia->publicImageUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                                    @else
                                        <div class="absolute inset-0 grid place-items-center px-4 text-center text-sm font-medium text-white/80">
                                            {{ __('No available image selected. The gradient will remain visible.') }}
                                        </div>
                                    @endif
                                    @if($sectionType === PageSectionType::Hero->value)
                                        <div class="absolute inset-0 bg-[linear-gradient(to_right,rgb(195,20,50),rgb(36,11,54))] opacity-95" aria-hidden="true"></div>
                                    @endif
                                    <div class="absolute inset-x-0 bottom-0 z-10 p-4 text-white">
                                        <p class="truncate font-semibold">{{ $this->selectedBackgroundMedia?->name ?? __('Gradient-only hero') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @unless($isWelcomeSectionType)
                        @if($isHeroSectionType)
                            <details class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                                <summary class="cursor-pointer text-sm font-semibold">{{ __('Advanced hero settings') }}</summary>
                                <div class="mt-4">
                                    <flux:textarea wire:model="settings" :label="__('Section settings (JSON)')" rows="9" class="font-mono text-sm" description="{{ __('Only optional, validated display settings belong here. Common hero settings are configured above.') }}" />
                                </div>
                            </details>
                        @else
                            <flux:textarea wire:model="settings" :label="__('Section settings (JSON)')" rows="9" class="font-mono text-sm" description="{{ __('Only validated JSON for the selected approved section type is stored.') }}" />
                        @endif
                    @endunless
                    <flux:switch wire:model="isVisible" :label="__('Visible')" />
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">{{ __('Save section') }}</span>
                            <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                        </flux:button>
                        @if($sectionId)
                            <flux:button type="button" wire:click="resetForm">{{ __('Cancel') }}</flux:button>
                        @endif
                    </div>
                </flux:card>
            </form>
        </div>
    </div>
</main>
