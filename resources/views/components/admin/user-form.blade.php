@props([
    'form',
    'roles',
    'people',
    'currentPhotoUrl' => null,
    'accountStatuses' => [],
    'accountStatus' => null,
    'showAccountCreationOptions' => false,
])

<div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.75fr)]">
    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-5">
                <flux:heading size="lg">{{ __('Account information') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Use a unique username and an email address the administrator controls.') }}</flux:text>
            </div>

            @if ($showAccountCreationOptions)
                <flux:callout variant="info" icon="key" class="mb-5">
                    <flux:callout.heading>{{ __('Temporary password') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('A temporary password will be generated automatically and shown once after creation. The user must replace it at first sign-in.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="form.name" :label="__('Full name')" autocomplete="name" required />
                <flux:input wire:model="form.username" :label="__('Username')" autocomplete="username" required />
                <flux:input wire:model="form.email" :label="__('Email address')" type="email" autocomplete="email" required class="sm:col-span-2" />
            </div>
        </section>

        @if ($showAccountCreationOptions)
            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mb-5">
                    <flux:heading size="lg">{{ __('Initial account status') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Active accounts can sign in immediately. Other statuses block sign-in until activated.') }}</flux:text>
                </div>

                <div class="space-y-5">
                    <flux:select wire:model.live="accountStatus" :label="__('Account status')">
                        @foreach ($accountStatuses as $status)
                            <flux:select.option :value="$status->value" wire:key="account-status-{{ $status->value }}">
                                {{ __($status->label()) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="accountStatus" />

                    @if ($accountStatus === \App\AccountStatus::Suspended->value)
                        <flux:textarea
                            wire:model="suspensionReason"
                            :label="__('Suspension reason')"
                            :description="__('Required when creating a suspended account.')"
                            rows="4"
                            required
                        />
                        <flux:error name="suspensionReason" />
                    @endif
                </div>
            </section>
        @endif

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-5">
                <flux:heading size="lg">{{ __('Administrative access') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Roles group administrative permissions. Ministry positions remain in Leadership.') }}</flux:text>
            </div>

            <flux:checkbox.group wire:model="form.roleIds" :label="__('Roles')" class="grid gap-3 sm:grid-cols-2">
                @foreach ($roles as $role)
                    <flux:checkbox
                        :value="$role->id"
                        :label="\App\RoleName::fromStoredName($role->name)?->label() ?? \Illuminate\Support\Str::headline($role->name)"
                        wire:key="user-role-{{ $role->id }}"
                    />
                @endforeach
            </flux:checkbox.group>
            <flux:error name="form.roleIds" />
            <flux:error name="form.roleIds.*" />
        </section>
    </div>

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-5">
                <flux:heading size="lg">{{ __('Profile photo') }}</flux:heading>
                <flux:text class="mt-1">{{ __('This image identifies the administrator account and does not replace a Leadership portrait.') }}</flux:text>
            </div>

            <div class="space-y-4">
                @if ($currentPhotoUrl)
                    <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/70">
                        <flux:avatar :src="$currentPhotoUrl" :name="$form->name" size="lg" />
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 dark:text-zinc-200">{{ __('Current photo') }}</p>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Upload a new image to replace it.') }}</p>
                        </div>
                    </div>
                @endif

                <flux:input
                    wire:model="form.photo"
                    :label="__('Photo')"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    description="{{ __('JPG, PNG, or WebP. Maximum 2 MB.') }}"
                />

                @if ($currentPhotoUrl)
                    <flux:checkbox wire:model="form.removePhoto" :label="__('Remove the current photo')" />
                @endif

                <div wire:loading wire:target="form.photo" class="text-sm text-church-maroon-700 dark:text-church-gold-400">
                    {{ __('Validating photo…') }}
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-5">
                <flux:heading size="lg">{{ __('Person link') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Optionally link this account to an existing person without duplicating their ministry profile.') }}</flux:text>
            </div>

            <flux:select wire:model="form.personId" :label="__('Existing person')">
                <flux:select.option value="">{{ __('No linked person') }}</flux:select.option>
                @foreach ($people as $person)
                    <flux:select.option :value="$person->id" wire:key="user-person-{{ $person->id }}">
                        {{ $person->full_name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </section>
    </div>
</div>
