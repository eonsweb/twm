<?php

use App\PermissionName;
use App\SystemSettingSection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('System Settings')] class extends Component
{
    #[Locked]
    public string $section;

    public string $mobileSection;

    public function mount(string $section): void
    {
        Gate::authorize(PermissionName::SettingsView->value);

        $resolvedSection = SystemSettingSection::tryFrom($section);
        abort_if($resolvedSection === null, 404);
        Gate::authorize($resolvedSection->permission()->value);

        $this->section = $resolvedSection->value;
        $this->mobileSection = $resolvedSection->value;
    }

    /**
     * @return list<SystemSettingSection>
     */
    #[Computed]
    public function availableSections(): array
    {
        return array_values(array_filter(
            SystemSettingSection::cases(),
            fn (SystemSettingSection $section): bool => auth()->user()?->can($section->permission()->value) === true,
        ));
    }

    public function updatedMobileSection(string $section): void
    {
        $resolvedSection = SystemSettingSection::tryFrom($section);

        abort_unless(
            $resolvedSection !== null
                && auth()->user()?->can($resolvedSection->permission()->value),
            403,
        );

        $this->redirectRoute($resolvedSection->routeName(), navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    <x-admin.page-header
        :title="__('System settings')"
        :description="__('Manage church identity, public website preferences, operational defaults, and protected integrations.')"
        :eyebrow="__('Administration')"
    />

    <div class="lg:hidden">
        <flux:select wire:model.live="mobileSection" :label="__('Settings section')">
            @foreach ($this->availableSections as $availableSection)
                <flux:select.option :value="$availableSection->value">
                    {{ __($availableSection->label()) }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <aside class="sticky top-24 hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:block dark:border-zinc-800 dark:bg-zinc-900">
            <flux:navlist aria-label="{{ __('System settings') }}">
                @foreach ($this->availableSections as $availableSection)
                    <flux:navlist.item
                        :href="route($availableSection->routeName())"
                        :current="$availableSection->value === $section"
                        :icon="$availableSection->icon()"
                        wire:navigate
                    >
                        {{ __($availableSection->label()) }}
                    </flux:navlist.item>
                @endforeach
            </flux:navlist>
        </aside>

        <section class="min-w-0">
            @if ($section === \App\SystemSettingSection::ServiceTimes->value)
                <livewire:settings.service-times :key="'settings-service-times'" />
            @elseif ($section === \App\SystemSettingSection::Branding->value)
                <livewire:settings.branding :key="'settings-branding'" />
            @else
                <livewire:settings.editor :section="$section" :key="'settings-'.$section" />
            @endif
        </section>
    </div>
</div>
