<?php

use App\Models\HomepageHeroSlide;
use App\Models\Media;
use App\Models\Page;
use App\Pages\HomepageHero;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Page $page;
    #[Locked] public ?int $slideId = null;
    #[Locked] public int $editorVersion = 0;
    public bool $editing = false;
    public array $form = [];
    public array $mediaIds = [];
    public array $mobileMediaIds = [];
    public array $posterMediaIds = [];
    public array $emblemMediaIds = [];
    public string $search = '';
    public string $status = '';

    public function mount(Page $page): void
    {
        Gate::authorize('manageSections', $page);
        abort_unless($page->is_homepage, 404);
        $this->page = $page;
    }

    #[Computed]
    public function slides(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return HomepageHeroSlide::query()->where('page_id', $this->page->id)
            ->with(['media', 'videoPosterMedia'])
            ->when($this->search !== '', fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->when(in_array($this->status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $this->status === 'active'))
            ->orderBy('sort_order')->orderBy('id')->paginate(10, pageName: 'heroPage');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('heroPage');
    }

    public function updatedStatus(): void
    {
        $this->resetPage('heroPage');
    }

    public function create(): void
    {
        Gate::authorize('manageSections', $this->page);
        $this->resetValidation();
        $this->slideId = null;
        $this->mediaIds = $this->mobileMediaIds = $this->posterMediaIds = $this->emblemMediaIds = [];
        $this->form = [
            'title' => '', 'description' => '', 'cta_text' => '', 'cta_url' => '',
            'secondary_cta_text' => '', 'secondary_cta_url' => '', 'overlay_opacity' => null,
            'sort_order' => (int) HomepageHeroSlide::query()->where('page_id', $this->page->id)->max('sort_order') + 1,
            'is_active' => true, 'starts_at' => '', 'ends_at' => '',
            'settings' => [...app(HomepageHero::class)->defaults(), 'variant' => 'default', 'eyebrow' => '', 'background_alt' => ''],
        ];
        $this->editorVersion++;
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $slide = $this->findSlide($id);
        $this->resetValidation();
        $this->slideId = $slide->id;
        $this->form = $slide->only(['title', 'description', 'cta_text', 'cta_url', 'secondary_cta_text', 'secondary_cta_url', 'overlay_opacity', 'sort_order', 'is_active']);
        $this->form['starts_at'] = $slide->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->form['ends_at'] = $slide->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->form['settings'] = [...app(HomepageHero::class)->defaults(), ...($slide->settings ?? []), 'background_alt' => data_get($slide->settings, 'background_alt', '')];
        foreach (['mediaIds' => 'media_id', 'mobileMediaIds' => 'mobile_media_id', 'posterMediaIds' => 'video_poster_media_id', 'emblemMediaIds' => 'emblem_media_id'] as $property => $column) {
            $this->{$property} = $slide->{$column} ? [$slide->{$column}] : [];
        }
        $this->editorVersion++;
        $this->editing = true;
    }

    public function save(): void
    {
        Gate::authorize('manageSections', $this->page);
        abort_unless($this->page->is_homepage, 404);
        $existing = $this->slideId ? $this->findSlide($this->slideId) : null;
        $urlRules = ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
            if (! preg_match('~^(?:/(?!/)[^\\\\\s]*|\#[^\s]*|https?://[^\s]+)$~i', $value)) {
                $fail(__('Use an http(s) URL, an internal path, or an anchor.'));
            }
        }];
        $rules = [
            'form.title' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string', 'max:20000'],
            'form.cta_text' => ['nullable', 'string', 'max:100'],
            'form.cta_url' => $urlRules,
            'form.secondary_cta_text' => ['nullable', 'string', 'max:100'],
            'form.secondary_cta_url' => $urlRules,
            'form.overlay_opacity' => ['nullable', 'integer', 'between:0,100'],
            'form.sort_order' => ['required', 'integer', 'between:0,2147483647'],
            'form.is_active' => ['required', 'boolean'],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', Rule::when(filled($this->form['starts_at'] ?? null), 'after_or_equal:form.starts_at')],
            'form.settings.variant' => ['required', Rule::in(['default', 'anniversary'])],
            'form.settings.eyebrow' => ['nullable', 'string', 'max:150'],
            'form.settings.anniversary_number' => ['nullable', 'string', 'max:20'],
            'form.settings.anniversary_unit' => ['nullable', 'string', 'max:30'],
            'form.settings.script_heading' => ['nullable', 'string', 'max:100'],
            'form.settings.theme' => ['nullable', 'string', 'max:500'],
            'form.settings.background_alt' => ['nullable', 'string', 'max:500'],
        ];
        foreach (['show_emblem', 'show_theme', 'show_description', 'show_primary_cta', 'show_secondary_cta', 'show_scroll_indicator'] as $key) {
            $rules['form.settings.'.$key] = ['boolean'];
        }
        foreach (['mediaIds', 'mobileMediaIds', 'posterMediaIds', 'emblemMediaIds'] as $property) {
            $rules[$property] = ['array', 'max:1'];
            $rules[$property.'.*'] = ['integer', Rule::exists(Media::class, 'id')->withoutTrashed()];
        }
        if (! $existing || $existing->media_id !== null) {
            $rules['mediaIds'][] = 'required';
            $rules['mediaIds'][] = 'min:1';
        }
        $data = $this->validate($rules)['form'];
        foreach (['starts_at', 'ends_at', 'overlay_opacity'] as $key) {
            $data[$key] = filled($data[$key]) ? $data[$key] : null;
        }
        $media = $this->selectedMedia('mediaIds');
        $data['media_id'] = $media?->id;
        $data['media_type'] = $media?->media_type->value ?? 'image';
        $data['mobile_media_id'] = $this->selectedMedia('mobileMediaIds')?->id;
        $data['video_poster_media_id'] = $this->selectedMedia('posterMediaIds', true)?->id;
        $data['emblem_media_id'] = $this->selectedMedia('emblemMediaIds', true)?->id;
        $data['settings'] = [...($existing?->settings ?? []), ...$data['settings']];
        $data['page_id'] = $this->page->id;
        ($existing ?? new HomepageHeroSlide)->fill($data)->save();
        $this->editing = false;
        unset($this->slides);
    }

    public function toggle(int $id): void
    {
        $slide = $this->findSlide($id);
        $slide->update(['is_active' => ! $slide->is_active]);
        unset($this->slides);
    }

    public function delete(int $id): void
    {
        $this->findSlide($id)->delete();
        $this->editing = false;
        unset($this->slides);
    }

    public function move(int $id, string $direction): void
    {
        $this->findSlide($id);
        abort_unless(in_array($direction, ['up', 'down'], true), 422);
        DB::transaction(function () use ($id, $direction): void {
            $slides = HomepageHeroSlide::query()->where('page_id', $this->page->id)->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $index = $slides->search(fn (HomepageHeroSlide $slide): bool => $slide->id === $id);
            $target = $index + ($direction === 'up' ? -1 : 1);
            if ($target < 0 || $target >= $slides->count()) {
                return;
            }
            $ordered = $slides->all();
            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
            foreach ($ordered as $position => $slide) {
                $slide->update(['sort_order' => $position]);
            }
        });
        unset($this->slides);
    }

    private function findSlide(int $id): HomepageHeroSlide
    {
        Gate::authorize('manageSections', $this->page);
        abort_unless($this->page->is_homepage, 404);

        return HomepageHeroSlide::query()->where('page_id', $this->page->id)->findOrFail($id);
    }

    private function selectedMedia(string $property, bool $imageOnly = false): ?Media
    {
        if ($this->{$property} === []) {
            return null;
        }
        $media = Media::query()->find($this->{$property}[0]);
        if (! HomepageHeroSlide::usableMedia($media, $imageOnly)) {
            throw ValidationException::withMessages([$property => __('Select an available, active, public image or supported MP4, WebM, or Ogg video from the Media Library.')]);
        }
        Gate::authorize('view', $media);

        return $media;
    }
};
?>

<flux:card class="space-y-5" id="homepage-hero-slides">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><flux:heading size="lg">{{ __('Hero slides') }}</flux:heading><flux:text>{{ __('Manage homepage slides. Disabled slides retain their content and order.') }}</flux:text></div>
        <flux:button wire:click="create" variant="primary">{{ __('Add Hero Slide') }}</flux:button>
    </div>
    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :label="__('Search slides')" />
        <flux:select wire:model.live="status" :label="__('Status')"><option value="">{{ __('All') }}</option><option value="active">{{ __('Active') }}</option><option value="inactive">{{ __('Inactive') }}</option></flux:select>
    </div>
    <div wire:loading class="text-sm" role="status">{{ __('Updating slides…') }}</div>
    @forelse($this->slides as $slide)
        <div wire:key="hero-slide-{{ $slide->id }}" class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            @if($thumbnail = $slide->media?->publicImageUrl() ?? $slide->videoPosterMedia?->publicImageUrl())<img src="{{ $thumbnail }}" alt="" class="h-16 w-24 rounded object-cover">@endif
            <div class="min-w-0 flex-1"><flux:heading>{{ $slide->title }}</flux:heading><flux:text>{{ ucfirst($slide->media_type) }} · {{ __('Order: :order', ['order' => $slide->sort_order]) }}</flux:text><flux:badge :color="$slide->is_active ? 'green' : 'zinc'">{{ $slide->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                @if($slide->starts_at || $slide->ends_at)<flux:text class="text-xs">{{ $slide->starts_at?->format('Y-m-d H:i') ?? __('Any time') }} — {{ $slide->ends_at?->format('Y-m-d H:i') ?? __('No end') }} ({{ config('app.timezone') }})</flux:text>@endif
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" wire:click="edit({{ $slide->id }})">{{ __('Edit') }}</flux:button>
                <flux:button size="sm" wire:click="toggle({{ $slide->id }})">{{ $slide->is_active ? __('Disable') : __('Enable') }}</flux:button>
                <flux:button size="sm" wire:click="move({{ $slide->id }}, 'up')" :aria-label="__('Move :title up', ['title' => $slide->title])">{{ __('Move Up') }}</flux:button>
                <flux:button size="sm" wire:click="move({{ $slide->id }}, 'down')" :aria-label="__('Move :title down', ['title' => $slide->title])">{{ __('Move Down') }}</flux:button>
                <flux:button size="sm" variant="danger" wire:click="delete({{ $slide->id }})" wire:confirm="{{ __('Are you sure you want to permanently delete this hero slide?') }}">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    @empty
        <flux:text>{{ __('No matching slides. When no slides are eligible, the existing hero configuration is displayed.') }}</flux:text>
    @endforelse
    {{ $this->slides->links() }}
    <flux:modal wire:model="editing" class="w-full md:max-w-3xl">
        @if($editing)
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $slideId ? __('Edit Hero Slide') : __('Add Hero Slide') }}</flux:heading>
            <flux:input wire:model="form.title" :label="__('Heading')" required />
            <flux:textarea wire:model="form.description" :label="__('Supporting text')" rows="4" />
            @foreach(['mediaIds' => __('Main image or video'), 'mobileMediaIds' => __('Mobile image or video (optional)'), 'posterMediaIds' => __('Video poster (optional)'), 'emblemMediaIds' => __('Anniversary emblem (optional)')] as $property => $label)
                <div wire:key="hero-picker-{{ $property }}-{{ $editorVersion }}"><flux:heading>{{ $label }}</flux:heading>
                    <livewire:media-picker wire:model="{{ $property }}" :allowed-types="in_array($property, ['posterMediaIds', 'emblemMediaIds']) ? ['image'] : ['image', 'video']" :allow-upload="false" :allow-private="false" :key="'hero-'.$property.'-'.$editorVersion" />
                    <flux:error :name="$property" /><flux:error :name="$property.'.*'" />
                </div>
            @endforeach
            <flux:input wire:model="form.settings.background_alt" :label="__('Image alt text (optional)')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.cta_text" :label="__('Primary CTA text')" /><flux:input wire:model="form.cta_url" :label="__('Primary CTA URL')" />
                <flux:input wire:model="form.secondary_cta_text" :label="__('Secondary CTA text')" /><flux:input wire:model="form.secondary_cta_url" :label="__('Secondary CTA URL')" />
                <flux:input type="number" min="0" wire:model="form.sort_order" :label="__('Display order')" />
                <flux:input type="number" min="0" max="100" wire:model="form.overlay_opacity" :label="__('Overlay opacity (%)')" :description="__('Leave blank to preserve the existing design.')" />
                <flux:input type="datetime-local" wire:model="form.starts_at" :label="__('Starts at (optional)')" /><flux:input type="datetime-local" wire:model="form.ends_at" :label="__('Ends at (optional)')" />
            </div>
            <flux:text>{{ __('Scheduling timezone: :timezone', ['timezone' => config('app.timezone')]) }}</flux:text>
            <flux:switch wire:model="form.is_active" :label="__('Active')" />
            <flux:select wire:model.live="form.settings.variant" :label="__('Existing hero design')"><option value="default">{{ __('Default') }}</option><option value="anniversary">{{ __('Anniversary') }}</option></flux:select>
            <flux:input wire:model="form.settings.eyebrow" :label="__('Eyebrow')" />
            @if(data_get($form, 'settings.variant') === 'anniversary')
                <div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="form.settings.anniversary_number" :label="__('Anniversary number')" /><flux:input wire:model="form.settings.anniversary_unit" :label="__('Anniversary unit')" /></div>
                <flux:input wire:model="form.settings.script_heading" :label="__('Script heading')" /><flux:textarea wire:model="form.settings.theme" :label="__('Anniversary theme')" />
                @foreach(['show_emblem' => __('Show emblem'), 'show_theme' => __('Show theme'), 'show_description' => __('Show description'), 'show_primary_cta' => __('Show primary CTA'), 'show_secondary_cta' => __('Show secondary CTA'), 'show_scroll_indicator' => __('Show scroll indicator')] as $key => $label)
                    <flux:switch wire:model="form.settings.{{ $key }}" :label="$label" />
                @endforeach
            @endif
            <div class="flex gap-3"><flux:button type="submit" variant="primary">{{ __('Save slide') }}</flux:button><flux:button wire:click="$set('editing', false)">{{ __('Cancel') }}</flux:button></div>
        </form>
        @endif
    </flux:modal>
</flux:card>
