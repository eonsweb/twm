<?php

use App\Actions\Leadership\SaveLeader;
use App\Livewire\Forms\LeadershipPersonForm;
use App\Models\LeadershipPosition;
use App\Models\Person;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Add leader')] class extends Component
{
    use WithFileUploads;

    public LeadershipPersonForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Person::class);

        if (! Gate::allows('publish', new Person)) {
            $this->form->isPublic = false;
        }
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
            ->doesntHave('person')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email']);
    }

    public function save(SaveLeader $saveLeader): void
    {
        Gate::authorize('create', Person::class);

        if (! Gate::allows('publish', new Person)) {
            $this->form->isPublic = false;
        }

        $this->form->normalize();
        $this->form->validate();

        $saveLeader->handle(
            $this->form->personData(),
            $this->form->assignmentData(),
            $this->form->portrait,
        );

        Flux::toast(variant: 'success', text: __('Leader created.'));
        $this->redirectRoute('leadership.index', navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('leadership.index')" wire:navigate>{{ __('Leadership') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Add leader') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="__('Add leader')"
        :description="__('Create a reusable person profile and assign public ministry positions.')"
        :eyebrow="__('Content management')"
    />

    <form wire:submit="save" class="space-y-6">
        <x-admin.leadership-form
            :form="$form"
            :positions="$this->positions"
            :users="$this->users"
            :can-publish="Gate::allows('publish', new Person)"
        />

        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200/80 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
            <flux:button :href="route('leadership.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                {{ __('Create leader') }}
            </flux:button>
        </div>
    </form>
</div>
