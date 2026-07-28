<?php

use App\AccountStatus;
use App\Actions\Users\ChangeUserStatus;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\SendUserInvitation;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Administrator details')] class extends Component
{
    public User $user;

    public bool $showSuspendModal = false;

    public string $suspensionReason = '';

    public function mount(User $user): void
    {
        Gate::authorize('view', $user);

        $this->user = $this->loadUser($user);
    }

    public function sendInvitation(SendUserInvitation $sendUserInvitation): void
    {
        Gate::authorize('sendInvitation', $this->user);

        $sendUserInvitation->handle($this->user);
        Flux::toast(variant: 'success', text: __('Password setup email sent.'));
    }

    public function deactivate(ChangeUserStatus $changeUserStatus): void
    {
        Gate::authorize('deactivate', $this->user);

        $this->user = $this->loadUser($changeUserStatus->deactivate(auth()->user(), $this->user));
        Flux::toast(variant: 'success', text: __('Account deactivated and sessions revoked.'));
    }

    public function activate(ChangeUserStatus $changeUserStatus): void
    {
        Gate::authorize('activate', $this->user);

        $this->user = $this->loadUser($changeUserStatus->activate(auth()->user(), $this->user));
        Flux::toast(variant: 'success', text: __('Account activated.'));
    }

    public function suspend(ChangeUserStatus $changeUserStatus): void
    {
        Gate::authorize('suspend', $this->user);

        $validated = $this->validate([
            'suspensionReason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $this->user = $this->loadUser(
            $changeUserStatus->suspend(auth()->user(), $this->user, $validated['suspensionReason']),
        );
        $this->reset(['showSuspendModal', 'suspensionReason']);

        Flux::toast(variant: 'success', text: __('Account suspended and sessions revoked.'));
    }

    public function delete(DeleteUser $deleteUser): void
    {
        Gate::authorize('delete', $this->user);

        $deleteUser->handle(auth()->user(), $this->user);
        Flux::toast(variant: 'success', text: __('Administrator account deleted.'));
        $this->redirectRoute('users.index', navigate: true);
    }

    private function loadUser(User $user): User
    {
        return $user->refresh()->load(['roles:id,name', 'person'])->loadCount('passkeys');
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('users.index')" wire:navigate>{{ __('Users') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $user->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="$user->name"
        :description="__('Administrator account details, access, and security status.')"
        :eyebrow="__('Administration')"
    >
        <x-slot:actions>
            @can('update', $user)
                <flux:button :href="route('users.edit', $user)" variant="primary" icon="pencil-square" wire:navigate>
                    {{ __('Edit administrator') }}
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
        <div class="space-y-6">
            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                    <flux:avatar :src="$user->photoUrl()" :name="$user->name" size="lg" color="auto" :color:seed="$user->id" class="size-20!" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="xl">{{ $user->name }}</flux:heading>
                            <flux:badge :color="$user->accountStatus()->badgeColor()">{{ __($user->accountStatus()->label()) }}</flux:badge>
                            @if (auth()->user()->is($user))
                                <flux:badge color="blue">{{ __('You') }}</flux:badge>
                            @endif
                        </div>
                        <flux:text class="mt-1">{{ '@'.$user->username }} · {{ $user->email }}</flux:text>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @forelse ($user->roles as $role)
                                <flux:badge size="sm" color="zinc" wire:key="details-role-{{ $role->id }}">
                                    {{ \App\RoleName::fromStoredName($role->name)?->label() ?? \Illuminate\Support\Str::headline($role->name) }}
                                </flux:badge>
                            @empty
                                <flux:badge size="sm" color="amber">{{ __('No administrative role') }}</flux:badge>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Account activity') }}</flux:heading>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Email verification') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $user->email_verified_at?->format('M j, Y · g:i A') ?? __('Pending') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Last login') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $user->last_login_at?->format('M j, Y · g:i A') ?? __('Never') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Last login IP') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $user->last_login_ip ?? __('Not recorded') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Created') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $user->created_at?->format('M j, Y · g:i A') }}</dd>
                    </div>
                </dl>

                @if ($user->account_status === AccountStatus::Suspended->value)
                    <flux:callout variant="danger" icon="no-symbol" class="mt-5">
                        <flux:callout.heading>{{ __('Suspended :date', ['date' => $user->suspended_at?->format('M j, Y · g:i A')]) }}</flux:callout.heading>
                        <flux:callout.text>{{ $user->suspension_reason }}</flux:callout.text>
                    </flux:callout>
                @endif
            </section>

            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Linked person') }}</flux:heading>
                @if ($user->person)
                    <div class="mt-4 flex items-center justify-between gap-4 rounded-lg bg-slate-50 p-4 dark:bg-zinc-800/70">
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-white">{{ $user->person->full_name }}</p>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('This is a separate reusable Person record.') }}</p>
                        </div>
                        @can('view', $user->person)
                            <flux:button :href="route('leadership.edit', $user->person)" variant="outline" size="sm" icon="identification" wire:navigate>
                                {{ __('View person') }}
                            </flux:button>
                        @endcan
                    </div>
                @else
                    <x-admin.empty-state icon="identification" :title="__('No linked person')" :description="__('This administrator is not linked to a ministry profile.')" class="min-h-36" />
                @endif
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Security') }}</flux:heading>
                <div class="mt-5 space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ __('Two-factor authentication') }}</p>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ $user->two_factor_confirmed_at ? __('Enabled') : __('Not enabled') }}</p>
                        </div>
                        <flux:badge :color="$user->two_factor_confirmed_at ? 'green' : 'zinc'" size="sm">{{ $user->two_factor_confirmed_at ? __('Enabled') : __('Off') }}</flux:badge>
                    </div>
                    <flux:separator />
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ __('Passkeys') }}</p>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ trans_choice(':count registered passkey|:count registered passkeys', $user->passkeys_count, ['count' => $user->passkeys_count]) }}</p>
                        </div>
                        <flux:badge :color="$user->passkeys_count > 0 ? 'green' : 'zinc'" size="sm">{{ $user->passkeys_count }}</flux:badge>
                    </div>
                </div>

                @can('sendInvitation', $user)
                    <flux:button wire:click="sendInvitation" variant="outline" icon="envelope" class="mt-5 w-full" wire:loading.attr="disabled">
                        {{ __('Send password setup/reset email') }}
                    </flux:button>
                @endcan
            </section>

            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Account controls') }}</flux:heading>
                <flux:text class="mt-1">{{ __('State changes revoke remembered sign-in and active database sessions.') }}</flux:text>

                <div class="mt-5 flex flex-col gap-2">
                    @if ($user->account_status === AccountStatus::Active->value)
                        @can('deactivate', $user)
                            <flux:button wire:click="deactivate" wire:confirm="{{ __('Deactivate :name and revoke their sessions?', ['name' => $user->name]) }}" variant="outline" icon="pause" class="w-full">
                                {{ __('Deactivate account') }}
                            </flux:button>
                        @endcan
                        @can('suspend', $user)
                            <flux:button wire:click="$set('showSuspendModal', true)" variant="danger" icon="no-symbol" class="w-full">
                                {{ __('Suspend account') }}
                            </flux:button>
                        @endcan
                    @else
                        @can('activate', $user)
                            <flux:button wire:click="activate" variant="primary" icon="check-circle" class="w-full">
                                {{ __('Activate account') }}
                            </flux:button>
                        @endcan
                    @endif

                    @can('delete', $user)
                        <flux:button
                            wire:click="delete"
                            wire:confirm="{{ __('Permanently delete :name’s account? Their linked Person record will be preserved. This cannot be undone.', ['name' => $user->name]) }}"
                            variant="ghost"
                            icon="trash"
                            class="w-full text-red-600 dark:text-red-400"
                        >
                            {{ __('Delete account') }}
                        </flux:button>
                    @endcan
                </div>
            </section>
        </div>
    </div>

    <flux:modal wire:model="showSuspendModal" class="max-w-lg">
        <form wire:submit="suspend" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Suspend :name', ['name' => $user->name]) }}</flux:heading>
                <flux:text class="mt-1">{{ __('The reason is stored with the account and should be factual and appropriate for administrators.') }}</flux:text>
            </div>
            <flux:textarea wire:model="suspensionReason" :label="__('Suspension reason')" rows="5" required />
            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showSuspendModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="danger" icon="no-symbol" wire:loading.attr="disabled">{{ __('Suspend account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
