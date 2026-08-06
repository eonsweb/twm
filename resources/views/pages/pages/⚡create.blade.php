<?php
use App\Actions\Pages\SavePage;
use App\Livewire\Forms\PageForm;
use App\Models\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public PageForm $form;
    public array $featuredMediaIds=[];
    public array $ogMediaIds=[];
    public function mount(): void { Gate::authorize('create',Page::class); $this->form->publishedAt=now()->format('Y-m-d\TH:i'); }
    #[Computed] public function parents() { return Page::query()->orderBy('title')->get(['id','title']); }
    public function save(SavePage $save): void { $this->form->normalize(); $this->form->featuredImageId=$this->featuredMediaIds[0]??null; $this->form->ogImageId=$this->ogMediaIds[0]??null; $this->form->validate(); $page=$save->handle(auth()->user(),$this->form->pageData()); $this->redirectRoute('pages.edit',$page,navigate:true); }
}; ?>
<main id="admin-main" class="p-4 sm:p-6 lg:p-8"><form wire:submit="save" class="mx-auto max-w-7xl space-y-6"><x-admin.page-header :title="__('Create page')" :description="__('Build a public page, publication settings, navigation, and SEO.')"><x-slot:actions><flux:button :href="route('pages.index')" wire:navigate>{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">{{ __('Save page') }}</flux:button></x-slot:actions></x-admin.page-header><x-admin.page-form :form="$form" :parents="$this->parents" /><flux:error name="form.isHomepage" /></form></main>
