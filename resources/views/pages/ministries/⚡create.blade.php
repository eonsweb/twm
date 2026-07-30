<?php

use App\Actions\Ministries\SaveMinistry;
use App\Livewire\Forms\MinistryForm;
use App\MinistryStatus;
use App\Models\Event;
use App\Models\Ministry;
use App\Models\Person;
use App\Models\Sermon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Create Ministry')] class extends Component
{
    use WithFileUploads;

    public MinistryForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Ministry::class);
    }

    #[Computed] public function people() { return Person::query()->leaders()->active()->orderBy('last_name')->orderBy('first_name')->get(['id', 'title', 'first_name', 'middle_name', 'last_name']); }
    #[Computed] public function sermons() { return Sermon::query()->latest('sermon_date')->limit(100)->get(['id', 'title']); }
    #[Computed] public function events() { return Event::query()->latest('starts_at')->limit(100)->get(['id', 'title']); }

    public function saveDraft(SaveMinistry $saveMinistry): void
    {
        $this->form->status = MinistryStatus::Draft->value;
        $this->persist($saveMinistry);
    }

    public function save(SaveMinistry $saveMinistry): void
    {
        $this->persist($saveMinistry);
    }

    private function persist(SaveMinistry $saveMinistry): void
    {
        if ($this->form->status === MinistryStatus::Published->value && $this->form->publishedAt === '') {
            $this->form->publishedAt = now()->format('Y-m-d\TH:i');
        }
        $this->form->normalize();
        $this->form->validate();
        $this->form->validateLeadership();
        $ministry = $saveMinistry->handle(
            Auth::user(), $this->form->ministryData(), $this->form->leaders,
            $this->form->sermonIds, $this->form->eventIds,
            $this->form->featuredImage, $this->form->logo,
        );
        session()->flash('success', __('Ministry created successfully.'));
        $this->redirectRoute('ministries.edit', $ministry, navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('ministries.index')" wire:navigate>{{ __('Ministries') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Create') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="__('Create ministry')" :description="__('Add ministry identity, leadership, meeting details, related content, and publishing settings.')" :eyebrow="__('Ministries')" />
    <form wire:submit="save" class="space-y-6">
        <x-admin.ministry-form :form="$form" :people="$this->people" :sermons="$this->sermons" :events="$this->events" />
        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <flux:button :href="route('ministries.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="button" wire:click="saveDraft">{{ __('Save as draft') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check">{{ __('Create ministry') }}</flux:button>
        </div>
    </form>
</div>
