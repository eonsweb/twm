<?php

use App\Actions\Sermons\SaveSermon;
use App\Livewire\Forms\SermonForm;
use App\Models\Person;
use App\Models\Ministry;
use App\Models\Sermon;
use App\Sermons\ExternalMedia;
use App\SermonStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Create Sermon')] class extends Component
{
    use WithFileUploads;

    public SermonForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Sermon::class);
        $this->form->sermonDate = now()->toDateString();
    }

    #[Computed]
    public function speakers()
    {
        return Person::query()->active()->orderBy('first_name')->orderBy('last_name')->get();
    }

    #[Computed]
    public function ministries()
    {
        return Ministry::query()->active()->ordered()->get(['id', 'name']);
    }

    public function inspectMedia(ExternalMedia $externalMedia): void
    {
        $this->form->normalize($externalMedia);
        $this->form->validateOnly('externalMediaUrl');
    }

    public function saveDraft(SaveSermon $saveSermon, ExternalMedia $externalMedia): void
    {
        $this->form->status = SermonStatus::Draft->value;
        $this->persist($saveSermon, $externalMedia);
    }

    public function save(SaveSermon $saveSermon, ExternalMedia $externalMedia): void
    {
        $this->persist($saveSermon, $externalMedia);
    }

    private function persist(SaveSermon $saveSermon, ExternalMedia $externalMedia): void
    {
        if ($this->form->status === SermonStatus::Published->value && $this->form->publishedAt === '') {
            $this->form->publishedAt = now()->format('Y-m-d\TH:i');
        }

        $this->form->normalize($externalMedia);
        $this->form->validate();
        $sermon = $saveSermon->handle(
            Auth::user(),
            $this->form->sermonData(),
            $this->form->thumbnail,
            ministryIds: $this->form->ministryIds,
        );

        session()->flash('success', __('Sermon created.'));
        $this->redirectRoute('sermons.edit', $sermon, navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('sermons.index')" wire:navigate>{{ __('Sermons') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Create') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header :title="__('Create sermon')" :description="__('Add an externally hosted sermon and control when it becomes public.')" :eyebrow="__('Sermons')" />

    <form wire:submit="save" class="space-y-6">
        <x-admin.sermon-form :form="$form" :speakers="$this->speakers" :ministries="$this->ministries" />
        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <flux:button :href="route('sermons.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="button" wire:click="saveDraft" wire:loading.attr="disabled">{{ __('Save as draft') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">{{ __('Save sermon') }}</flux:button>
        </div>
    </form>
</div>
