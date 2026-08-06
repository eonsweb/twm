<?php
use App\Actions\Pages\SavePage;
use App\Livewire\Forms\PageForm;
use App\Models\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public Page $page; public PageForm $form; public array $featuredMediaIds=[]; public array $ogMediaIds=[];
    public function mount(Page $page): void { Gate::authorize('update',$page); $this->page=$page; $this->form->setPage($page); $this->featuredMediaIds=array_filter([$page->featured_image_id]); $this->ogMediaIds=array_filter([$page->og_image_id]); }
    #[Computed] public function parents() { return Page::query()->whereKeyNot($this->page)->orderBy('title')->get(['id','title']); }
    public function save(SavePage $save): void { Gate::authorize('update',$this->page); $this->form->normalize(); $this->form->featuredImageId=$this->featuredMediaIds[0]??null; $this->form->ogImageId=$this->ogMediaIds[0]??null; $this->form->validate(); $this->page=$save->handle(auth()->user(),$this->form->pageData(),$this->page); \Flux\Flux::toast(__('Page saved.')); }
    public function preview(): void { Gate::authorize('preview',$this->page); $this->redirect(URL::temporarySignedRoute('pages.preview',now()->addMinutes(30),['page'=>$this->page]),navigate:false); }
}; ?>
<main id="admin-main" class="p-4 sm:p-6 lg:p-8"><form wire:submit="save" class="mx-auto max-w-7xl space-y-6"><x-admin.page-header :title="__('Edit :title',['title'=>$page->title])" :description="__('Update content, publication settings, navigation, and SEO.')"><x-slot:actions>@can(\App\PermissionName::PagesPreview->value)<flux:button type="button" wire:click="preview" icon="eye">{{ __('Preview') }}</flux:button>@endcan @can(\App\PermissionName::PagesManageSections->value)<flux:button :href="route('pages.sections',$page)" wire:navigate icon="rectangle-stack">{{ __('Sections') }}</flux:button>@endcan<flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">{{ __('Save changes') }}</flux:button></x-slot:actions></x-admin.page-header><x-admin.page-form :form="$form" :parents="$this->parents" /><flux:error name="form.isHomepage" /></form></main>
