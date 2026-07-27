@php
    $user = auth()->user();
    $roleName = $user->getRoleNames()->first() ?? __('Team member');
@endphp

<flux:dropdown position="bottom" align="end">
    <button
        type="button"
        class="flex min-h-11 items-center gap-2 rounded-lg px-1.5 py-1 text-start transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-church-gold-500 sm:gap-3 sm:px-2 dark:hover:bg-zinc-800"
        aria-label="{{ __('Open account menu') }}"
        data-test="sidebar-menu-button"
    >
        <flux:avatar
            :src="$user->photoUrl()"
            :name="$user->name"
            :initials="$user->initials()"
            class="bg-church-maroon-900! text-white!"
        />
        <span class="hidden min-w-0 sm:block">
            <span class="block max-w-36 truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $user->name }}</span>
            <span class="block max-w-36 truncate text-xs text-slate-500 dark:text-zinc-400">{{ $roleName }}</span>
        </span>
        <flux:icon.chevron-down class="hidden size-4 text-slate-500 sm:block dark:text-zinc-400" />
    </button>

    <flux:menu class="min-w-64">
        <div class="flex items-center gap-3 px-2 py-2 text-start text-sm">
            <flux:avatar
                :src="$user->photoUrl()"
                :name="$user->name"
                :initials="$user->initials()"
                class="bg-church-maroon-900! text-white!"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ $user->name }}</flux:heading>
                <flux:text class="truncate">{{ $user->email }}</flux:text>
                <flux:text class="truncate text-xs">{{ $roleName }}</flux:text>
            </div>
        </div>
        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="user-circle" wire:navigate>
                {{ __('Profile settings') }}
            </flux:menu.item>
            <flux:menu.item :href="route('appearance.edit')" icon="swatch" wire:navigate>
                {{ __('Appearance') }}
            </flux:menu.item>
            <flux:menu.item :href="route('home')" icon="arrow-top-right-on-square" wire:navigate>
                {{ __('View church website') }}
            </flux:menu.item>
        </flux:menu.radio.group>
        <flux:menu.separator />
        <flux:menu.radio.group>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
