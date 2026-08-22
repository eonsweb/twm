<?php

use App\Actions\Events\SaveEventType;
use App\Models\EventType;
use App\Support\EventIcons;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Event Types')] class extends Component
{
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $eventTypeId = null;
    public string $name = '';
    public string $description = '';
    public string $color = 'emerald';
    public string $icon = 'calendar-days';
    public bool $isActive = true;
    public int $sortOrder = 0;

    public function mount(): void
    {
        Gate::authorize('viewAny', EventType::class);
    }

    #[Computed]
    public function eventTypes()
    {
        return EventType::query()->withCount('events')->orderBy('sort_order')->orderBy('name')->get();
    }

    public function create(): void
    {
        Gate::authorize('create', EventType::class);
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function edit(int $eventTypeId): void
    {
        $eventType = EventType::findOrFail($eventTypeId);
        Gate::authorize('update', $eventType);
        $this->eventTypeId = $eventType->id;
        $this->name = $eventType->name;
        $this->description = $eventType->description ?? '';
        $this->color = $eventType->color ?? 'emerald';
        $this->icon = $eventType->icon ?? 'calendar-days';
        $this->isActive = $eventType->is_active;
        $this->sortOrder = $eventType->sort_order;
        $this->showFormModal = true;
    }

    public function save(SaveEventType $saveEventType): void
    {
        $eventType = $this->eventTypeId === null ? null : EventType::findOrFail($this->eventTypeId);
        $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique(EventType::class, 'name')->ignore($this->eventTypeId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', Rule::in(['emerald', 'blue', 'amber', 'rose', 'violet', 'cyan', 'indigo', 'slate', 'yellow', 'teal', 'zinc'])],
            'icon' => ['nullable', Rule::in(EventIcons::values())],
            'isActive' => ['boolean'],
            'sortOrder' => ['required', 'integer', 'min:0'],
        ]);
        $saveEventType->handle(Auth::user(), [
            'name' => str($this->name)->squish()->toString(),
            'description' => $this->description ?: null,
            'color' => $this->color ?: null,
            'icon' => $this->icon ?: null,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
        ], $eventType);

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->eventTypes);
        Flux::toast(variant: 'success', text: __('Event type saved successfully.'));
    }

    public function confirmDelete(int $eventTypeId): void
    {
        $eventType = EventType::findOrFail($eventTypeId);
        Gate::authorize('delete', $eventType);
        $this->eventTypeId = $eventTypeId;
        $this->showDeleteModal = true;
    }

    public function delete(SaveEventType $saveEventType): void
    {
        $saveEventType->delete(Auth::user(), EventType::findOrFail($this->eventTypeId));
        $this->reset(['eventTypeId', 'showDeleteModal']);
        unset($this->eventTypes);
        Flux::toast(variant: 'success', text: __('Event type removed successfully.'));
    }

    private function resetForm(): void
    {
        $this->reset(['eventTypeId', 'name', 'description', 'icon']);
        $this->color = 'emerald';
        $this->icon = 'calendar-days';
        $this->isActive = true;
        $this->sortOrder = 0;
        $this->resetValidation();
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('events.index')" wire:navigate>{{ __('Events') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Event types') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header :title="__('Event types')" :description="__('Organize events into reusable church programme categories.')" :eyebrow="__('Events')">
        <x-slot:actions>@can('create', EventType::class)<flux:button wire:click="create" variant="primary" icon="plus">{{ __('Add event type') }}</flux:button>@endcan</x-slot:actions>
    </x-admin.page-header>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        @if ($this->eventTypes->isEmpty())
            <div class="p-6"><x-admin.empty-state icon="tag" :title="__('No event types')" :description="__('Create the first event type.')" /></div>
        @else
            <div class="overflow-x-auto p-4 sm:p-6">
                <flux:table>
                    <flux:table.columns><flux:table.column class="ps-4">{{ __('Name') }}</flux:table.column><flux:table.column>{{ __('Description') }}</flux:table.column><flux:table.column>{{ __('Events') }}</flux:table.column><flux:table.column>{{ __('Status') }}</flux:table.column><flux:table.column align="end" class="pe-4">{{ __('Actions') }}</flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->eventTypes as $eventType)
                            <flux:table.row :key="$eventType->id" wire:key="event-type-row-{{ $eventType->id }}">
                                <flux:table.cell class="ps-4"><div class="flex items-center gap-3"><span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300"><flux:icon :name="$eventType->icon ?? \App\Support\EventIcons::DEFAULT" class="size-5" /></span><div><p class="font-semibold">{{ $eventType->name }}</p><p class="text-xs text-slate-500">{{ $eventType->slug }}</p></div></div></flux:table.cell>
                                <flux:table.cell><p class="max-w-md">{{ str($eventType->description)->limit(100) }}</p></flux:table.cell>
                                <flux:table.cell>{{ $eventType->events_count }}</flux:table.cell>
                                <flux:table.cell><flux:badge :color="$eventType->is_active ? 'green' : 'zinc'">{{ $eventType->is_active ? __('Active') : __('Inactive') }}</flux:badge></flux:table.cell>
                                <flux:table.cell align="end" class="pe-4"><div class="flex justify-end gap-1">@can('update', $eventType)<flux:button wire:click="edit({{ $eventType->id }})" variant="ghost" size="sm" icon="pencil-square" :aria-label="__('Edit :name', ['name' => $eventType->name])" />@endcan @can('delete', $eventType)<flux:button wire:click="confirmDelete({{ $eventType->id }})" variant="ghost" size="sm" icon="trash" :aria-label="__('Delete :name', ['name' => $eventType->name])" />@endcan</div></flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>

    <flux:modal wire:model="showFormModal" class="max-w-2xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $eventTypeId ? __('Edit event type') : __('Create event type') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:textarea wire:model="description" :label="__('Description')" rows="4" />
            <div class="grid gap-4 sm:grid-cols-2"><flux:select wire:model="color" :label="__('Badge color')">@foreach (['emerald', 'blue', 'amber', 'rose', 'violet', 'cyan', 'indigo', 'slate', 'yellow', 'teal', 'zinc'] as $item)<flux:select.option :value="$item">{{ str($item)->headline() }}</flux:select.option>@endforeach</flux:select><flux:input wire:model="sortOrder" :label="__('Sort order')" type="number" min="0" /></div>
            <flux:select wire:model="icon" :label="__('Icon')">@foreach (EventIcons::options() as $value => $label)<flux:select.option :value="$value">{{ $label }}</flux:select.option>@endforeach</flux:select>
            <flux:switch wire:model="isActive" :label="__('Active')" />
            <div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Save event type') }}</flux:button></div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-lg">
        <form wire:submit="delete" class="space-y-5"><div><flux:heading size="lg">{{ __('Remove event type?') }}</flux:heading><flux:text class="mt-2">{{ __('Types in use are deactivated so existing events retain their category history.') }}</flux:text></div><div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="danger">{{ __('Remove') }}</flux:button></div></form>
    </flux:modal>
</div>
