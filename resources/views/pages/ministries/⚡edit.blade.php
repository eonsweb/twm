<?php

use App\Actions\Ministries\SaveMinistry;
use App\Livewire\Forms\MinistryForm;
use App\MinistryStatus;
use App\Models\Event;
use App\Models\Ministry;
use App\Models\Person;
use App\Models\Sermon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit Ministry')] class extends Component
{
    use WithFileUploads;

    public MinistryForm $form;
    public Ministry $ministry;

    public function mount(Ministry $ministry): void
    {
        Gate::authorize('update', $ministry);
        abort_if($ministry->trashed(), 404);
        $this->ministry = $ministry;
        $this->form->setMinistry($ministry);
    }

    #[Computed] public function people() { return Person::query()->leaders()->orderBy('last_name')->orderBy('first_name')->get(['id', 'title', 'first_name', 'middle_name', 'last_name']); }
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
        $this->ministry = $saveMinistry->handle(
            Auth::user(), $this->form->ministryData(), $this->form->leaders,
            $this->form->sermonIds, $this->form->eventIds,
            $this->form->featuredImage, $this->form->logo,
            $this->form->removeFeaturedImage, $this->form->removeLogo, $this->ministry,
        );
        $this->form->setMinistry($this->ministry);
        $this->form->featuredImage = $this->form->logo = null;
        Flux::toast(variant: 'success', text: __('Ministry updated successfully.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('ministries.index')" wire:navigate>{{ __('Ministries') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Edit') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
    <x-admin.page-header :title="__('Edit ministry')" :description="$ministry->name" :eyebrow="__('Ministries')"><x-slot:actions><flux:button :href="route('ministries.show', $ministry)" icon="eye" wire:navigate>{{ __('View') }}</flux:button></x-slot:actions></x-admin.page-header>
    <form wire:submit="save" class="space-y-6">
        <x-admin.ministry-form :form="$form" :people="$this->people" :sermons="$this->sermons" :events="$this->events" :current-image-url="$ministry->imageUrl()" :current-logo-url="$ministry->logoUrl()" />
        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <flux:button :href="route('ministries.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="button" wire:click="saveDraft">{{ __('Save as draft') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check">{{ __('Update ministry') }}</flux:button>
        </div>
    </form>
</div>
