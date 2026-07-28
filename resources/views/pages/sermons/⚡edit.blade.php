<?php

use App\Actions\Sermons\SaveSermon;
use App\Livewire\Forms\SermonForm;
use App\Models\Person;
use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\Topic;
use App\Sermons\ExternalMedia;
use App\SermonStatus;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit Sermon')] class extends Component
{
    use WithFileUploads;

    public Sermon $sermon;

    public SermonForm $form;

    public function mount(Sermon $sermon): void
    {
        Gate::authorize('update', $sermon);
        $this->sermon = $sermon;
        $this->form->setSermon($sermon);
    }

    #[Computed]
    public function speakers()
    {
        return Person::query()->active()->orderBy('first_name')->orderBy('last_name')->get();
    }

    #[Computed]
    public function series()
    {
        return SermonSeries::query()->orderBy('title')->get();
    }

    #[Computed]
    public function topics()
    {
        return Topic::query()->active()->orderBy('name')->get();
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
        $this->sermon = $saveSermon->handle(
            Auth::user(),
            $this->form->sermonData(),
            $this->form->topicIds,
            $this->form->thumbnail,
            $this->form->removeThumbnail,
            $this->sermon,
        );
        $this->form->setSermon($this->sermon);
        $this->form->thumbnail = null;

        Flux::toast(variant: 'success', text: __('Sermon updated.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('sermons.index')" wire:navigate>{{ __('Sermons') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Edit') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <x-admin.page-header :title="__('Edit sermon')" :description="$sermon->title" :eyebrow="__('Sermons')">
        <x-slot:actions>
            <flux:button :href="route('sermons.show', $sermon)" icon="eye" wire:navigate>{{ __('Preview') }}</flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <form wire:submit="save" class="space-y-6">
        <x-admin.sermon-form
            :form="$form"
            :speakers="$this->speakers"
            :series="$this->series"
            :topics="$this->topics"
            :current-thumbnail-url="$sermon->thumbnailUrl()"
        />
        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <flux:button :href="route('sermons.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="button" wire:click="saveDraft" wire:loading.attr="disabled">{{ __('Save as draft') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">{{ __('Update sermon') }}</flux:button>
        </div>
    </form>
</div>
