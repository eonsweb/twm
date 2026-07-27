@props([
    'label',
    'icon',
    'permission',
    'routeName',
    'activePattern',
])

@can($permission)
    @if (Route::has($routeName))
        <flux:sidebar.item
            :icon="$icon"
            :href="route($routeName)"
            :current="request()->routeIs($activePattern)"
            class="admin-sidebar-item"
            wire:navigate
        >
            {{ $label }}
        </flux:sidebar.item>
    @else
        <div
            class="flex min-h-9 items-center gap-3 rounded-lg px-3 py-2 text-sm text-white/55"
            aria-disabled="true"
            title="{{ __('This module is not available yet') }}"
        >
            <flux:icon :icon="$icon" class="size-4 shrink-0" />
            <span class="truncate">{{ $label }}</span>
            <span class="sr-only">{{ __('Not available yet') }}</span>
        </div>
    @endif
@endcan
