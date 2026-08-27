<?php

use App\MediaStatus;
use App\MediaType;
use App\MediaVisibility;
use App\Models\Media;
use App\PermissionName;
use App\Settings\BrandingMedia;
use App\Settings\SettingManager;
use App\Settings\SettingRegistry;
use App\SettingType;
use App\SystemSettingSection;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /** @var array<string, list<int>> */
    public array $mediaIds = [];

    /** @var array<string, list<int>> */
    public array $initialMediaIds = [];

    /** @var array<string, string|null> */
    public array $legacyPaths = [];

    /** @var array<string, bool> */
    public array $remove = [];

    /** @var array<string, mixed> */
    public array $values = [];

    public function mount(SettingRegistry $registry, SettingManager $settings): void
    {
        Gate::authorize(PermissionName::SettingsBrandingUpdate->value);

        foreach ($registry->forSection(SystemSettingSection::Branding) as $definition) {
            if ($definition['type'] === SettingType::Image) {
                $storedValue = $settings->get('branding', $definition['key']);
                $selectedIds = $this->isMediaId($storedValue) ? [(int) $storedValue] : [];

                $this->mediaIds[$definition['key']] = $selectedIds;
                $this->initialMediaIds[$definition['key']] = $selectedIds;
                $this->legacyPaths[$definition['key']] = is_string($storedValue) && ! $this->isMediaId($storedValue)
                    ? $storedValue
                    : null;
                $this->remove[$definition['key']] = false;
            } else {
                $this->values[$definition['key']] = $settings->get(
                    $definition['group'],
                    $definition['key'],
                    $definition['default'],
                );
            }
        }
    }

    public function save(SettingRegistry $registry, SettingManager $settings): void
    {
        Gate::authorize(PermissionName::SettingsBrandingUpdate->value);

        $definitions = $registry->forSection(SystemSettingSection::Branding);
        $changedImages = collect($definitions)
            ->filter(fn (array $definition): bool => $definition['type'] === SettingType::Image)
            ->filter(function (array $definition): bool {
                $key = $definition['key'];

                return ($this->remove[$key] ?? false)
                    || ($this->mediaIds[$key] ?? []) !== ($this->initialMediaIds[$key] ?? []);
            })
            ->values();
        $rules = [];

        foreach ($definitions as $definition) {
            if ($definition['type'] !== SettingType::Image) {
                $rules['values.'.$definition['key']] = $definition['rules'];
            }
        }

        foreach ($changedImages as $definition) {
            $rules['mediaIds.'.$definition['key']] = ['array', 'max:1'];
            $rules['mediaIds.'.$definition['key'].'.*'] = ['integer'];
        }

        $this->validate($rules);
        $selectedIds = $changedImages
            ->map(fn (array $definition): mixed => data_get($this->mediaIds, $definition['key'].'.0'))
            ->filter(fn (mixed $id): bool => $id !== null)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique();
        $selectedMedia = Media::query()->whereKey($selectedIds)->get()->keyBy('id');
        $updates = [];

        foreach ($changedImages as $definition) {
            $key = $definition['key'];
            $selectedId = data_get($this->mediaIds, $key.'.0');

            if ($selectedId !== null) {
                $media = $selectedMedia->get((int) $selectedId);
                $isValid = $media instanceof Media
                    && $media->media_type === MediaType::Image
                    && $media->visibility === MediaVisibility::Public
                    && $media->status === MediaStatus::Active
                    && $media->existsOnDisk()
                    && ($key !== 'favicon' || in_array(strtolower($media->extension), $this->allowedExtensions($key), true));

                if (! $isValid) {
                    throw ValidationException::withMessages([
                        'mediaIds.'.$key => __('Choose an available, active, public image from the Media Library.'),
                    ]);
                }

                Gate::authorize('view', $media);
            }

            $updates[] = [...$definition, 'value' => $selectedId === null ? null : (int) $selectedId];
        }

        foreach ($definitions as $definition) {
            if ($definition['type'] !== SettingType::Image) {
                $updates[] = [...$definition, 'value' => $this->values[$definition['key']]];
            }
        }

        $settings->putMany($updates);

        foreach ($changedImages as $definition) {
            $key = $definition['key'];
            $this->initialMediaIds[$key] = $this->mediaIds[$key] ?? [];
            $this->legacyPaths[$key] = null;
            $this->remove[$key] = false;
        }

        unset($this->previewUrls);
        Flux::toast(__('Branding settings saved.'));
    }

    public function removeMedia(string $key): void
    {
        Gate::authorize(PermissionName::SettingsBrandingUpdate->value);
        abort_unless(collect($this->imageDefinitions)->contains('key', $key), 404);

        $this->mediaIds[$key] = [];
        $this->remove[$key] = true;
        unset($this->previewUrls);
    }

    /** @return list<string> */
    public function allowedExtensions(string $key): array
    {
        return $key === 'favicon' ? ['ico', 'png', 'svg', 'webp'] : [];
    }

    /** @return array<string, string|null> */
    #[Computed]
    public function previewUrls(): array
    {
        $values = [];

        foreach ($this->imageDefinitions as $definition) {
            $key = $definition['key'];
            $values[$key] = data_get($this->mediaIds, $key.'.0') ?? $this->legacyPaths[$key] ?? null;
        }

        return app(BrandingMedia::class)->urls($values);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function imageDefinitions(): array
    {
        return array_values(array_filter(
            app(SettingRegistry::class)->forSection(SystemSettingSection::Branding),
            fn (array $definition): bool => $definition['type'] === SettingType::Image,
        ));
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function colorDefinitions(): array
    {
        return array_values(array_filter(
            app(SettingRegistry::class)->forSection(SystemSettingSection::Branding),
            fn (array $definition): bool => $definition['type'] !== SettingType::Image,
        ));
    }

    private function isMediaId(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && ctype_digit($value));
    }
};
?>

<form wire:submit="save" class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-slate-200 px-5 py-5 sm:px-6 dark:border-zinc-800">
            <h2 class="text-lg font-semibold text-slate-950 dark:text-white">{{ __('Branding') }}</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-zinc-400">
                {{ __('Choose reusable images from the Media Library. Removing a selection does not delete the original asset.') }}
            </p>
        </div>

        <div class="grid gap-5 p-5 md:grid-cols-2 sm:p-6">
            @foreach ($this->imageDefinitions as $definition)
                @php
                    $key = $definition['key'];
                    $previewUrl = $this->previewUrls[$key] ?? null;
                    $hasSelection = ($mediaIds[$key] ?? []) !== [] || filled($legacyPaths[$key] ?? null);
                @endphp

                <div wire:key="branding-{{ $key }}" class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                    <div class="mb-4 flex aspect-[16/7] items-center justify-center overflow-hidden rounded-lg bg-slate-100 dark:bg-zinc-800">
                        @if ($previewUrl)
                            <img src="{{ $previewUrl }}" alt="{{ __('Preview of :label', ['label' => __($definition['label'])]) }}" class="h-full w-full object-contain p-3">
                        @else
                            <flux:icon.photo class="size-10 text-slate-400" />
                        @endif
                    </div>

                    <p class="text-sm font-medium text-slate-950 dark:text-white">{{ __($definition['label']) }}</p>

                    @if ($key === 'homepage_hero_image')
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-zinc-400">
                            {{ __('Used only by the legacy homepage fallback. Managed homepage heroes use Pages → Home → Sections.') }}
                        </p>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <livewire:media-picker
                            :key="'branding-picker-'.$key"
                            wire:model.deep="mediaIds.{{ $key }}"
                            :allowed-types="[MediaType::Image->value]"
                            :allowed-extensions="$this->allowedExtensions($key)"
                            :multiple="false"
                            :maximum="1"
                            collection="branding-{{ $key }}"
                            :allow-upload="false"
                            :show-selected-assets="false"
                            :button-label="__('Choose from Media Library')"
                            :button-aria-label="__('Choose :label from Media Library', ['label' => strtolower(__($definition['label']))])"
                        />

                        @if ($hasSelection)
                            <flux:button
                                type="button"
                                size="sm"
                                variant="ghost"
                                wire:click="removeMedia('{{ $key }}')"
                                :aria-label="__('Remove :label', ['label' => strtolower(__($definition['label']))])"
                            >
                                {{ __('Remove') }}
                            </flux:button>
                        @endif
                    </div>

                    <flux:error name="mediaIds.{{ $key }}" />
                    <flux:error name="mediaIds.{{ $key }}.*" />
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <h3 class="font-semibold text-slate-950 dark:text-white">{{ __('Brand colors') }}</h3>
        <div class="mt-4 grid gap-5 sm:grid-cols-2">
            @foreach ($this->colorDefinitions as $definition)
                <flux:input wire:model="values.{{ $definition['key'] }}" type="color" :label="__($definition['label'])" />
            @endforeach
        </div>
    </div>

    <div class="sticky bottom-4 flex justify-end rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
        <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ __('Save branding') }}</span>
            <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
        </flux:button>
    </div>
</form>
