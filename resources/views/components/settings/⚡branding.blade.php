<?php

use App\PermissionName;
use App\Settings\SettingManager;
use App\Settings\SettingRegistry;
use App\SettingType;
use App\SystemSettingSection;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    /** @var array<string, mixed> */
    public array $uploads = [];

    /** @var array<string, string|null> */
    public array $currentPaths = [];

    /** @var array<string, bool> */
    public array $remove = [];

    /** @var array<string, mixed> */
    public array $values = [];

    public function mount(SettingRegistry $registry, SettingManager $settings): void
    {
        Gate::authorize(PermissionName::SettingsBrandingUpdate->value);

        foreach ($registry->forSection(SystemSettingSection::Branding) as $definition) {
            if ($definition['type'] === SettingType::Image) {
                $this->currentPaths[$definition['key']] = $settings->get('branding', $definition['key']);
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
        $rules = [];

        foreach ($definitions as $definition) {
            $prefix = $definition['type'] === SettingType::Image ? 'uploads.' : 'values.';
            $rules[$prefix.$definition['key']] = $definition['rules'];
        }

        $this->validate($rules);

        $updates = [];
        $newPaths = [];
        $oldPaths = [];

        try {
            foreach ($definitions as $definition) {
                $key = $definition['key'];

                if ($definition['type'] !== SettingType::Image) {
                    $updates[] = [...$definition, 'value' => $this->values[$key]];

                    continue;
                }

                $upload = $this->uploads[$key] ?? null;
                $shouldRemove = $this->remove[$key] ?? false;

                if ($upload === null && ! $shouldRemove) {
                    continue;
                }

                $newPath = $upload?->store('system-settings/branding', 'public');

                if ($newPath !== null) {
                    $newPaths[] = $newPath;
                }

                if ($this->currentPaths[$key] !== null) {
                    $oldPaths[$key] = $this->currentPaths[$key];
                }

                $updates[] = [...$definition, 'value' => $newPath];
            }

            $settings->putMany($updates);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPaths);

            throw $exception;
        }

        foreach ($oldPaths as $key => $oldPath) {
            if (! $settings->isStoredPathReferenced($oldPath, 'branding', $key)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $this->reset('uploads');

        foreach ($this->imageDefinitions as $definition) {
            $key = $definition['key'];
            $this->currentPaths[$key] = $settings->get('branding', $key);
            $this->remove[$key] = false;
        }

        Flux::toast(__('Branding settings saved.'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function imageDefinitions(): array
    {
        return array_values(array_filter(
            app(SettingRegistry::class)->forSection(SystemSettingSection::Branding),
            fn (array $definition): bool => $definition['type'] === SettingType::Image,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function colorDefinitions(): array
    {
        return array_values(array_filter(
            app(SettingRegistry::class)->forSection(SystemSettingSection::Branding),
            fn (array $definition): bool => $definition['type'] !== SettingType::Image,
        ));
    }
};
?>

<form wire:submit="save" class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-slate-200 px-5 py-5 sm:px-6 dark:border-zinc-800">
            <h2 class="text-lg font-semibold text-slate-950 dark:text-white">{{ __('Branding') }}</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-zinc-400">
                {{ __('Upload optimized artwork. Replaced files are removed only when no other setting references them.') }}
            </p>
        </div>

        <div class="grid gap-5 p-5 md:grid-cols-2 sm:p-6">
            @foreach ($this->imageDefinitions as $definition)
                @php
                    $key = $definition['key'];
                    $temporaryUpload = $uploads[$key] ?? null;
                    $currentPath = $currentPaths[$key] ?? null;
                @endphp

                <div wire:key="branding-{{ $key }}" class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                    <div class="mb-4 flex aspect-[16/7] items-center justify-center overflow-hidden rounded-lg bg-slate-100 dark:bg-zinc-800">
                        @if ($temporaryUpload)
                            <img src="{{ $temporaryUpload->temporaryUrl() }}" alt="" class="h-full w-full object-contain p-3">
                        @elseif ($currentPath && ! ($remove[$key] ?? false))
                            <img src="{{ Storage::disk('public')->url($currentPath) }}" alt="" class="h-full w-full object-contain p-3">
                        @else
                            <flux:icon.photo class="size-10 text-slate-400" />
                        @endif
                    </div>

                    <flux:input
                        wire:model="uploads.{{ $key }}"
                        type="file"
                        accept=".png,.jpg,.jpeg,.webp,.svg,.ico"
                        :label="__($definition['label'])"
                    />

                    @if ($currentPath)
                        <div class="mt-3">
                            <flux:switch wire:model="remove.{{ $key }}" :label="__('Remove current file')" />
                        </div>
                    @endif

                    <div wire:loading wire:target="uploads.{{ $key }}" class="mt-2 text-xs text-slate-500">
                        {{ __('Uploading preview…') }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <h3 class="font-semibold text-slate-950 dark:text-white">{{ __('Brand colors') }}</h3>
        <div class="mt-4 grid gap-5 sm:grid-cols-2">
            @foreach ($this->colorDefinitions as $definition)
                <flux:input
                    wire:model="values.{{ $definition['key'] }}"
                    type="color"
                    :label="__($definition['label'])"
                />
            @endforeach
        </div>
    </div>

    <div class="sticky bottom-4 flex justify-end rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
        <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">{{ __('Save branding') }}</span>
            <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
        </flux:button>
    </div>
</form>
