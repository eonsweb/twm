@props([
    'form',
    'positions',
    'users',
    'currentPortraitUrl' => null,
    'canPublish' => false,
])

<div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-5">
                <flux:heading size="lg">{{ __('Personal information') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Record the person once, whether they are a leader, minister, or future sermon speaker.') }}</flux:text>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="form.title" :label="__('Title')" :placeholder="__('e.g. Rev., Prophet')" />
                <flux:select wire:model="form.userId" :label="__('Associated user account')">
                    <flux:select.option value="">{{ __('No associated account') }}</flux:select.option>
                    @foreach ($users as $user)
                        <flux:select.option :value="$user->id" wire:key="user-option-{{ $user->id }}">
                            {{ $user->name }} · {{ $user->email }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.firstName" :label="__('First name')" required />
                <flux:input wire:model="form.middleName" :label="__('Middle name')" />
                <flux:input wire:model="form.lastName" :label="__('Last name')" required />
                <flux:input wire:model="form.slug" :label="__('URL slug')" :placeholder="__('Generated from the name when blank')" />
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-5">
                <flux:heading size="lg">{{ __('Public profile') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Add only verified public details. Unknown information can remain blank.') }}</flux:text>
            </div>

            <div class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="form.email" :label="__('Public email')" type="email" />
                    <flux:input wire:model="form.phone" :label="__('Public phone')" type="tel" />
                </div>

                <flux:input
                    wire:model="form.portrait"
                    :label="__('Portrait')"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    description="{{ __('JPG, PNG, or WebP. Maximum 2 MB.') }}"
                />

                @if ($currentPortraitUrl)
                    <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/70">
                        <flux:avatar :src="$currentPortraitUrl" size="lg" />
                        <div>
                            <p class="text-sm font-medium text-slate-800 dark:text-zinc-200">{{ __('Current portrait') }}</p>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Choose a new file only when replacing it.') }}</p>
                        </div>
                    </div>
                @endif

                <div wire:loading wire:target="form.portrait" class="text-sm text-church-maroon-700 dark:text-church-gold-400">
                    {{ __('Validating portrait…') }}
                </div>

                <flux:textarea wire:model="form.shortBio" :label="__('Short biography')" rows="3" />
                <flux:textarea wire:model="form.biography" :label="__('Full biography')" rows="8" />

                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="form.websiteUrl" :label="__('Website')" type="text" placeholder="https://example.com" />
                    <flux:input wire:model="form.facebookUrl" :label="__('Facebook')" type="text" placeholder="https://facebook.com/…" />
                    <flux:input wire:model="form.instagramUrl" :label="__('Instagram')" type="text" placeholder="https://instagram.com/…" />
                    <flux:input wire:model="form.youtubeUrl" :label="__('YouTube')" type="text" placeholder="https://youtube.com/…" />
                </div>
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-5">
                <flux:heading size="lg">{{ __('Leadership information') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Assign one or more public ministry positions.') }}</flux:text>
            </div>

            <div class="space-y-5">
                <flux:field>
                    <flux:label>{{ __('Positions') }}</flux:label>
                    <div class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-2 xl:grid-cols-1 dark:border-zinc-700">
                        @foreach ($positions as $position)
                            <flux:checkbox
                                wire:model.live="form.positionIds"
                                :value="$position->id"
                                :label="$position->name"
                                wire:key="position-option-{{ $position->id }}"
                            />
                        @endforeach
                    </div>
                    <flux:error name="form.positionIds" />
                </flux:field>

                <flux:select wire:model="form.primaryPositionId" :label="__('Primary position')">
                    <flux:select.option value="">{{ __('Select a primary position') }}</flux:select.option>
                    @foreach ($positions->whereIn('id', array_map('intval', $form->positionIds)) as $position)
                        <flux:select.option :value="$position->id" wire:key="primary-position-{{ $position->id }}">
                            {{ $position->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.displayTitle" :label="__('Public display title')" />
                <flux:input wire:model="form.sortOrder" :label="__('Display order')" type="number" min="0" />

                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                    <flux:input wire:model="form.startedAt" :label="__('Started')" type="date" />
                    <flux:input wire:model="form.endedAt" :label="__('Ended')" type="date" />
                </div>

                <flux:switch wire:model="form.isCurrent" :label="__('Current assignment')" />
                <flux:switch wire:model="form.isActive" :label="__('Active profile')" />

                @if ($canPublish)
                    <flux:switch wire:model="form.isPublic" :label="__('Visible publicly')" />
                @else
                    <flux:callout variant="warning" icon="eye-slash">
                        <flux:callout.heading>{{ __('Public visibility restricted') }}</flux:callout.heading>
                        <flux:callout.text>{{ __('You can save profile details, but publishing requires the leadership publish permission.') }}</flux:callout.text>
                    </flux:callout>
                @endif
            </div>
        </section>
    </div>
</div>
