@props(['event'])

<flux:dropdown position="bottom" align="end">
    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('Actions for :title', ['title' => $event->title])" />
    <flux:menu>
        @if (! $event->deleted_at)
            <flux:menu.item :href="route('events.show', $event)" icon="eye" wire:navigate>{{ __('View') }}</flux:menu.item>
            @can('update', $event)<flux:menu.item :href="route('events.edit', $event)" icon="pencil-square" wire:navigate>{{ __('Edit') }}</flux:menu.item>@endcan
            @can('duplicate', $event)<flux:menu.item wire:click="confirm({{ $event->id }}, 'duplicate')" icon="document-duplicate">{{ __('Duplicate') }}</flux:menu.item>@endcan
            @if ($event->status !== \App\EventStatus::Published)
                @can('publish', $event)<flux:menu.item wire:click="confirm({{ $event->id }}, 'publish')" icon="globe-alt">{{ __('Publish') }}</flux:menu.item>@endcan
            @else
                @can('publish', $event)<flux:menu.item wire:click="confirm({{ $event->id }}, 'unpublish')" icon="eye-slash">{{ __('Unpublish') }}</flux:menu.item>@endcan
            @endif
            @if ($event->status !== \App\EventStatus::Completed)
                @can('complete', $event)<flux:menu.item wire:click="confirm({{ $event->id }}, 'complete')" icon="check-circle">{{ __('Mark completed') }}</flux:menu.item>@endcan
            @endif
            @if ($event->status !== \App\EventStatus::Cancelled)
                @can('cancel', $event)<flux:menu.item wire:click="confirm({{ $event->id }}, 'cancel')" icon="x-circle">{{ __('Cancel event') }}</flux:menu.item>@endcan
            @endif
            @can('delete', $event)<flux:menu.item wire:click="confirm({{ $event->id }}, 'delete')" icon="trash" variant="danger">{{ __('Delete') }}</flux:menu.item>@endcan
        @else
            @can('restore', $event)<flux:menu.item wire:click="confirm({{ $event->id }}, 'restore')" icon="arrow-uturn-left">{{ __('Restore') }}</flux:menu.item>@endcan
        @endif
    </flux:menu>
</flux:dropdown>
