<?php

use App\Activity\ActivityLogger;
use App\Models\Topic;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sermon Topics')] class extends Component
{
    public bool $showForm = false;
    public ?int $topicId = null;
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('viewAny', Topic::class);
    }

    #[Computed]
    public function topics()
    {
        return Topic::query()->withCount('sermons')->orderBy('name')->paginate(20);
    }

    public function create(): void
    {
        Gate::authorize('create', Topic::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $topic = Topic::findOrFail($id);
        Gate::authorize('update', $topic);
        $this->topicId = $topic->id;
        $this->name = $topic->name;
        $this->slug = $topic->slug;
        $this->description = $topic->description ?? '';
        $this->isActive = $topic->is_active;
        $this->showForm = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $topic = $this->topicId ? Topic::findOrFail($this->topicId) : new Topic;
        Gate::authorize($topic->exists ? 'update' : 'create', $topic->exists ? $topic : Topic::class);
        $this->slug = Str::slug($this->slug ?: $this->name);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:120', Rule::unique(Topic::class, 'slug')->ignore($this->topicId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['boolean'],
        ]);
        $creating = ! $topic->exists;
        $topic->fill(['name' => $validated['name'], 'slug' => $validated['slug'], 'description' => $validated['description'] ?: null, 'is_active' => $validated['isActive']])->save();
        $logger->log(logName: 'sermons', event: $creating ? 'sermon_topic.created' : 'sermon_topic.updated', description: ($creating ? 'Created' : 'Updated')." sermon topic '{$topic->name}'.", subject: $topic, causer: Auth::user(), newValues: ['name' => $topic->name, 'slug' => $topic->slug, 'active' => $topic->is_active]);
        $this->resetForm();
        unset($this->topics);
        Flux::toast(variant: 'success', text: __('Topic saved.'));
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'topicId', 'name', 'slug', 'description']);
        $this->isActive = true;
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('sermons.index')" wire:navigate>{{ __('Sermons') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Topics') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="__('Sermon topics')" :description="__('Maintain the shared vocabulary used for sermon discovery.')" :eyebrow="__('Sermons')"><x-slot:actions>@can('create', Topic::class)<flux:button wire:click="create" variant="primary" icon="plus">{{ __('Add topic') }}</flux:button>@endcan</x-slot:actions></x-admin.page-header>
    <section class="rounded-xl border border-slate-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">@if ($this->topics->isEmpty())<x-admin.empty-state icon="tag" :title="__('No topics')" :description="__('Create a reusable topic for sermons.')" />@else<flux:table :paginate="$this->topics"><flux:table.columns><flux:table.column>{{ __('Topic') }}</flux:table.column><flux:table.column>{{ __('Sermons') }}</flux:table.column><flux:table.column>{{ __('Active') }}</flux:table.column><flux:table.column align="end">{{ __('Actions') }}</flux:table.column></flux:table.columns><flux:table.rows>@foreach ($this->topics as $topic)<flux:table.row :key="$topic->id" wire:key="topic-row-{{ $topic->id }}"><flux:table.cell><p class="font-semibold">{{ $topic->name }}</p><p class="text-xs text-slate-500">/{{ $topic->slug }}</p></flux:table.cell><flux:table.cell>{{ $topic->sermons_count }}</flux:table.cell><flux:table.cell><flux:badge :color="$topic->is_active ? 'green' : 'zinc'">{{ $topic->is_active ? __('Yes') : __('No') }}</flux:badge></flux:table.cell><flux:table.cell align="end">@can('update', $topic)<flux:button wire:click="edit({{ $topic->id }})" size="sm" variant="ghost" icon="pencil-square" />@endcan</flux:table.cell></flux:table.row>@endforeach</flux:table.rows></flux:table>@endif</section>
    <flux:modal wire:model="showForm" class="max-w-xl"><form wire:submit="save" class="space-y-5"><flux:heading size="lg">{{ $topicId ? __('Edit topic') : __('Add topic') }}</flux:heading><flux:input wire:model="name" :label="__('Name')" required /><flux:input wire:model="slug" :label="__('Slug')" /><flux:textarea wire:model="description" :label="__('Description')" rows="4" /><flux:switch wire:model="isActive" :label="__('Active')" /><div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary">{{ __('Save topic') }}</flux:button></div></form></flux:modal>
</div>
