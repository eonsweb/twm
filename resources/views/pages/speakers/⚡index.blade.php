<?php

use App\Actions\Sermons\SaveSpeaker;
use App\Models\Person;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Sermon Speakers')] class extends Component
{
    use WithFileUploads;

    public bool $showForm = false;
    public ?int $speakerId = null;
    public string $title = '';
    public string $firstName = '';
    public string $middleName = '';
    public string $lastName = '';
    public string $slug = '';
    public string $shortBio = '';
    public string $biography = '';
    public bool $isActive = true;
    public bool $isPublic = true;
    public mixed $photo = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Person::class);
    }

    #[Computed]
    public function speakers()
    {
        return Person::query()->withCount(['sermons', 'leadershipAssignments'])->orderBy('first_name')->orderBy('last_name')->paginate(20);
    }

    public function create(): void
    {
        Gate::authorize('manageSpeakers', Person::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manageSpeakers', Person::class);
        $speaker = Person::findOrFail($id);
        $this->speakerId = $speaker->id;
        $this->title = $speaker->title ?? '';
        $this->firstName = $speaker->first_name;
        $this->middleName = $speaker->middle_name ?? '';
        $this->lastName = $speaker->last_name;
        $this->slug = $speaker->slug;
        $this->shortBio = $speaker->short_bio ?? '';
        $this->biography = $speaker->biography ?? '';
        $this->isActive = $speaker->is_active;
        $this->isPublic = $speaker->is_public;
        $this->showForm = true;
    }

    public function save(SaveSpeaker $saveSpeaker): void
    {
        Gate::authorize('manageSpeakers', Person::class);
        $speaker = $this->speakerId ? Person::findOrFail($this->speakerId) : null;
        $this->slug = Str::slug($this->slug ?: trim($this->firstName.' '.$this->lastName));
        $validated = $this->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'firstName' => ['required', 'string', 'max:255'],
            'middleName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:255', Rule::unique(Person::class, 'slug')->ignore($this->speakerId)],
            'shortBio' => ['nullable', 'string', 'max:1000'],
            'biography' => ['nullable', 'string', 'max:20000'],
            'isActive' => ['boolean'],
            'isPublic' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $saveSpeaker->handle(Auth::user(), [
            'title' => $validated['title'] ?: null,
            'first_name' => $validated['firstName'],
            'middle_name' => $validated['middleName'] ?: null,
            'last_name' => $validated['lastName'],
            'slug' => $validated['slug'],
            'short_bio' => $validated['shortBio'] ?: null,
            'biography' => $validated['biography'] ?: null,
            'is_active' => $validated['isActive'],
            'is_public' => $validated['isPublic'],
        ], $this->photo, $speaker);
        $this->resetForm();
        unset($this->speakers);
        Flux::toast(variant: 'success', text: __('Speaker saved.'));
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'speakerId', 'title', 'firstName', 'middleName', 'lastName', 'slug', 'shortBio', 'biography', 'photo']);
        $this->isActive = true;
        $this->isPublic = true;
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('sermons.index')" wire:navigate>{{ __('Sermons') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Speakers') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="__('Sermon speakers')" :description="__('Leadership profiles and guest ministers share one person record, preventing duplicate identities.')" :eyebrow="__('Sermons')"><x-slot:actions>@can('manageSpeakers', Person::class)<flux:button wire:click="create" variant="primary" icon="plus">{{ __('Add guest speaker') }}</flux:button>@endcan</x-slot:actions></x-admin.page-header>
    <section class="rounded-xl border border-slate-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">@if ($this->speakers->isEmpty())<x-admin.empty-state icon="microphone" :title="__('No speakers')" :description="__('Add a guest speaker or create a leadership profile.')" />@else<flux:table :paginate="$this->speakers"><flux:table.columns><flux:table.column>{{ __('Speaker') }}</flux:table.column><flux:table.column>{{ __('Type') }}</flux:table.column><flux:table.column>{{ __('Sermons') }}</flux:table.column><flux:table.column>{{ __('Visibility') }}</flux:table.column><flux:table.column align="end">{{ __('Actions') }}</flux:table.column></flux:table.columns><flux:table.rows>@foreach ($this->speakers as $speaker)<flux:table.row :key="$speaker->id" wire:key="speaker-row-{{ $speaker->id }}"><flux:table.cell><div class="flex items-center gap-3">@if ($speaker->photo_path)<flux:avatar :src="\Illuminate\Support\Facades\Storage::disk('public')->url($speaker->photo_path)" />@else<flux:avatar :name="$speaker->full_name" />@endif<div><p class="font-semibold">{{ $speaker->full_name }}</p><p class="text-xs text-slate-500">/{{ $speaker->slug }}</p></div></div></flux:table.cell><flux:table.cell>{{ $speaker->leadership_assignments_count ? __('Leader') : __('Guest') }}</flux:table.cell><flux:table.cell>{{ $speaker->sermons_count }}</flux:table.cell><flux:table.cell><flux:badge :color="$speaker->is_active && $speaker->is_public ? 'green' : 'zinc'">{{ $speaker->is_active && $speaker->is_public ? __('Public') : __('Hidden') }}</flux:badge></flux:table.cell><flux:table.cell align="end">@can('manageSpeakers', Person::class)<flux:button wire:click="edit({{ $speaker->id }})" size="sm" variant="ghost" icon="pencil-square" />@endcan</flux:table.cell></flux:table.row>@endforeach</flux:table.rows></flux:table>@endif</section>
    <flux:modal wire:model="showForm" class="max-w-2xl"><form wire:submit="save" class="space-y-5"><flux:heading size="lg">{{ $speakerId ? __('Edit speaker') : __('Add guest speaker') }}</flux:heading><div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="title" :label="__('Title')" /><flux:input wire:model="slug" :label="__('Slug')" /><flux:input wire:model="firstName" :label="__('First name')" required /><flux:input wire:model="middleName" :label="__('Middle name')" /><flux:input wire:model="lastName" :label="__('Last name')" required /><flux:input wire:model="photo" :label="__('Photo')" type="file" accept="image/jpeg,image/png,image/webp" /></div><flux:textarea wire:model="shortBio" :label="__('Short biography')" rows="3" /><flux:textarea wire:model="biography" :label="__('Biography')" rows="6" /><div class="flex gap-6"><flux:switch wire:model="isActive" :label="__('Active')" /><flux:switch wire:model="isPublic" :label="__('Public')" /></div><div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary">{{ __('Save speaker') }}</flux:button></div></form></flux:modal>
</div>
