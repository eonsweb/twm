<?php

use App\PermissionName;
use App\Mail\SettingsTestMail;
use App\Settings\SettingManager;
use App\Settings\SettingRegistry;
use App\SettingType;
use App\SystemSettingSection;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $section;

    /** @var array<string, mixed> */
    public array $values = [];

    public string $testRecipient = '';

    public function mount(string $section, SettingRegistry $registry, SettingManager $settings): void
    {
        $resolvedSection = SystemSettingSection::from($section);
        Gate::authorize($resolvedSection->permission()->value);

        $this->section = $section;

        foreach ($registry->forSection($resolvedSection) as $definition) {
            $value = $definition['encrypted']
                ? ''
                : $settings->get($definition['group'], $definition['key'], $definition['default']);

            $this->values[$definition['key']] = $definition['type'] === SettingType::Json
                ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : $value;
        }

        $this->testRecipient = auth()->user()?->email ?? '';
    }

    public function save(SettingRegistry $registry, SettingManager $settings): void
    {
        $section = SystemSettingSection::from($this->section);
        Gate::authorize($section->permission()->value);
        $this->ensurePasswordIsConfirmed($section);

        $validated = $this->validate($this->validationRules($registry), [], $this->settingValidationAttributes($registry));
        $values = $validated['values'];

        foreach ($registry->forSection($section) as $definition) {
            if ($definition['type'] === SettingType::Json && array_key_exists($definition['key'], $values)) {
                $values[$definition['key']] = json_decode($values[$definition['key']], true, flags: JSON_THROW_ON_ERROR);
            }
        }

        if ($section === SystemSettingSection::Donations) {
            $suggestedAmounts = $values['suggested_amounts'] ?? [];

            if (! is_array($suggestedAmounts) || collect($suggestedAmounts)->contains(
                fn (mixed $amount): bool => ! is_numeric($amount) || (float) $amount <= 0,
            )) {
                throw ValidationException::withMessages([
                    'values.suggested_amounts' => __('Every suggested donation amount must be a positive number.'),
                ]);
            }
        }

        $settings->updateSection($section, $values);

        foreach ($registry->forSection($section) as $definition) {
            if ($definition['encrypted']) {
                $this->values[$definition['key']] = '';
            }
        }

        Flux::toast(__('Settings saved.'));
    }

    public function sendTestEmail(): void
    {
        abort_unless($this->section === SystemSettingSection::Email->value, 404);
        Gate::authorize(PermissionName::SettingsEmailUpdate->value);
        $this->ensurePasswordIsConfirmed(SystemSettingSection::Email);

        $validated = $this->validate([
            'testRecipient' => ['required', 'email:rfc', 'max:255'],
        ]);

        try {
            Mail::to($validated['testRecipient'])->send(new SettingsTestMail);
        } catch (Throwable $exception) {
            Log::warning('A system settings test email failed.', [
                'exception_class' => $exception::class,
            ]);

            Flux::toast(variant: 'danger', text: __('The test email could not be sent. Check the server mail configuration and try again.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Test email sent.'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function definitions(): array
    {
        return app(SettingRegistry::class)->forSection(SystemSettingSection::from($this->section));
    }

    #[Computed]
    public function resolvedSection(): SystemSettingSection
    {
        return SystemSettingSection::from($this->section);
    }

    /**
     * @return array<string, list<string>>
     */
    private function validationRules(SettingRegistry $registry): array
    {
        return collect($registry->forSection(SystemSettingSection::from($this->section)))
            ->mapWithKeys(fn (array $definition): array => [
                'values.'.$definition['key'] => $definition['rules'],
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function settingValidationAttributes(SettingRegistry $registry): array
    {
        return collect($registry->forSection(SystemSettingSection::from($this->section)))
            ->mapWithKeys(fn (array $definition): array => [
                'values.'.$definition['key'] => strtolower($definition['label']),
            ])
            ->all();
    }

    private function ensurePasswordIsConfirmed(SystemSettingSection $section): void
    {
        if (! $section->requiresPasswordConfirmation()) {
            return;
        }

        $confirmedAt = (int) session('auth.password_confirmed_at', 0);
        $timeout = (int) config('auth.password_timeout', 10800);

        abort_if($confirmedAt < now()->subSeconds($timeout)->timestamp, 403);
    }
};
?>

<form wire:submit="save" class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-slate-200 px-5 py-5 sm:px-6 dark:border-zinc-800">
            <h2 class="text-lg font-semibold text-slate-950 dark:text-white">
                {{ __($this->resolvedSection->label()) }}
            </h2>
            <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-zinc-400">
                {{ __($this->resolvedSection->description()) }}
            </p>
        </div>

        <div class="grid gap-6 p-5 sm:grid-cols-2 sm:p-6">
            @foreach ($this->definitions as $definition)
                <div
                    wire:key="setting-{{ $definition['group'] }}-{{ $definition['key'] }}"
                    @class([
                        'sm:col-span-2' => in_array($definition['type'], [\App\SettingType::Text, \App\SettingType::Json], true),
                    ])
                >
                    @if ($definition['type'] === \App\SettingType::Boolean)
                        <flux:switch
                            wire:model="values.{{ $definition['key'] }}"
                            :label="__($definition['label'])"
                            :description="$definition['description'] ? __($definition['description']) : null"
                        />
                    @elseif ($definition['type'] === \App\SettingType::Text || $definition['type'] === \App\SettingType::Json)
                        <flux:textarea
                            wire:model="values.{{ $definition['key'] }}"
                            :label="__($definition['label'])"
                            :description="$definition['description'] ? __($definition['description']) : null"
                            rows="{{ $definition['type'] === \App\SettingType::Json ? 5 : 4 }}"
                        />
                    @elseif ($definition['options'] !== [])
                        <flux:select
                            wire:model="values.{{ $definition['key'] }}"
                            :label="__($definition['label'])"
                            :description="$definition['description'] ? __($definition['description']) : null"
                        >
                            @foreach ($definition['options'] as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ __($optionLabel) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:input
                            wire:model="values.{{ $definition['key'] }}"
                            :type="match ($definition['type']) {
                                \App\SettingType::Integer, \App\SettingType::Decimal => 'number',
                                \App\SettingType::Date => 'date',
                                \App\SettingType::Time => 'time',
                                \App\SettingType::Secret => 'password',
                                default => 'text',
                            }"
                            :step="$definition['type'] === \App\SettingType::Decimal ? 'any' : null"
                            :label="__($definition['label'])"
                            :description="$definition['description'] ? __($definition['description']) : null"
                            :autocomplete="$definition['type'] === \App\SettingType::Secret ? 'new-password' : null"
                        />
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @if ($section === \App\SystemSettingSection::Email->value)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <h3 class="font-semibold text-slate-950 dark:text-white">{{ __('Send a test email') }}</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-zinc-400">
                {{ __('This uses the active server mail configuration and never displays stored credentials.') }}
            </p>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <flux:input wire:model="testRecipient" type="email" :label="__('Recipient')" />
                </div>
                <flux:button type="button" wire:click="sendTestEmail" variant="outline" icon="paper-airplane">
                    {{ __('Send test') }}
                </flux:button>
            </div>
        </div>
    @endif

    <div class="sticky bottom-4 flex items-center justify-between rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
        <p wire:dirty class="text-sm font-medium text-amber-700 dark:text-amber-300">
            {{ __('You have unsaved changes.') }}
        </p>
        <span wire:dirty.remove></span>

        <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">{{ __('Save settings') }}</span>
            <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
        </flux:button>
    </div>
</form>
