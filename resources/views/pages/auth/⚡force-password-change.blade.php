<?php

use App\Actions\Users\CreateAdminUser;
use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.auth'), Title('Change temporary password')] class extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (! Auth::user()->must_change_password) {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'password' => [
                ...$this->passwordRules(),
                Rule::notIn([CreateAdminUser::TEMPORARY_PASSWORD]),
            ],
        ], [
            'password.not_in' => __('Choose a password other than the temporary password.'),
        ]);

        Auth::user()->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }
        $this->reset('password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password changed. You now have full account access.'));
        $this->redirectRoute('dashboard', navigate: true);
    }
};
?>

<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Choose a new password')"
        :description="__('Your administrator created this account with a temporary password. Replace it before continuing.')"
    />

    <flux:callout variant="warning" icon="key">
        <flux:callout.heading>{{ __('Password change required') }}</flux:callout.heading>
        <flux:callout.text>{{ __('Choose a secure password that you do not use for another account.') }}</flux:callout.text>
    </flux:callout>

    <form wire:submit="updatePassword" class="flex flex-col gap-6">
        <flux:input
            wire:model="password"
            :label="__('New password')"
            type="password"
            required
            autofocus
            autocomplete="new-password"
            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            viewable
        />

        <flux:input
            wire:model="password_confirmation"
            :label="__('Confirm new password')"
            type="password"
            required
            autocomplete="new-password"
            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            viewable
        />

        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
            {{ __('Change password and continue') }}
        </flux:button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <flux:button type="submit" variant="ghost" class="w-full">
            {{ __('Log out') }}
        </flux:button>
    </form>
</div>
