<?php

use App\Actions\Leadership\SaveLeader;
use App\Livewire\Forms\LeadershipPersonForm;
use App\Models\LeadershipPosition;
use App\Models\Person;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit leader')] class extends Component
{
    use WithFileUploads;

    public Person $person;

    public LeadershipPersonForm $form;

    public function mount(Person $person): void
    {
        Gate::authorize('update', $person);

        $this->person = $person;
        $this->form->setPerson($person);
    }

    #[Computed]
    public function positions()
    {
        return LeadershipPosition::query()
            ->orderBy('rank')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->where(function ($query): void {
                $query->doesntHave('person')
                    ->orWhere('id', $this->person->user_id);
            })
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email']);
    }

    public function save(SaveLeader $saveLeader): void
    {
        Gate::authorize('update', $this->person);

        if ($this->form->isPublic !== $this->person->is_public) {
            Gate::authorize('publish', $this->person);
        }

        $this->form->normalize();
        $this->form->validate();

        $this->person = $saveLeader->handle(
            $this->form->personData(),
            $this->form->assignmentData(),
            $this->form->portrait,
            $this->person,
        );

        $this->form->portrait = null;
        Flux::toast(variant: 'success', text: __('Leader updated.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('leadership.index')" wire:navigate>{{ __('Leadership') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Edit :name', ['name' => $person->full_name]) }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="__('Edit :name', ['name' => $person->full_name])"
        :description="__('Update public profile details and current leadership assignments.')"
        :eyebrow="__('Content management')"
    />

    <form wire:submit="save" class="space-y-6">
        <x-admin.leadership-form
            :form="$form"
            :positions="$this->positions"
            :users="$this->users"
            :current-portrait-url="$person->photo_path ? Storage::disk('public')->url($person->photo_path) : null"
            :can-publish="Gate::allows('publish', $person)"
        />

        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200/80 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
            <flux:button :href="route('leadership.index')" variant="ghost" wire:navigate>{{ __('Back') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                {{ __('Save changes') }}
            </flux:button>
        </div>
    </form>
</div>
