<?php

use App\Actions\Books\SaveBook;
use App\BookStatus;
use App\Livewire\Forms\BookForm;
use App\Models\Book;
use App\Models\Person;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Book')] class extends Component
{
    public BookForm $form;
    public Book $book;

    public function mount(Book $book): void
    {
        Gate::authorize('update', $book);
        abort_if($book->trashed(), 404);
        $this->book = $book;
        $this->form->setBook($book);
    }

    #[Computed]
    public function leaders()
    {
        return Person::query()->leaders()->active()->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'title', 'first_name', 'middle_name', 'last_name']);
    }

    #[Computed]
    public function speakers()
    {
        return Person::query()->active()->whereHas('sermons')->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'title', 'first_name', 'middle_name', 'last_name']);
    }

    public function saveDraft(SaveBook $saveBook): void
    {
        $this->form->status = BookStatus::Draft->value;
        $this->persist($saveBook);
    }

    public function save(SaveBook $saveBook): void
    {
        $this->persist($saveBook);
    }

    private function persist(SaveBook $saveBook): void
    {
        if ($this->form->status === BookStatus::Published->value && $this->form->publishedAt === '') {
            $this->form->publishedAt = now()->format('Y-m-d\TH:i');
        }

        $this->form->normalize();
        $this->form->validate();
        $this->form->validateBusinessRules();
        $this->book = $saveBook->handle(Auth::user(), $this->form->bookData(), $this->book);
        $this->form->setBook($this->book);
        Flux::toast(variant: 'success', text: __('Book updated successfully.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('books.index')" wire:navigate>{{ __('Books') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Edit') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
    <x-admin.page-header :title="__('Edit book')" :description="$book->title" :eyebrow="__('Books')"><x-slot:actions><flux:button :href="route('books.show', $book)" icon="eye" wire:navigate>{{ __('View') }}</flux:button></x-slot:actions></x-admin.page-header>
    <form wire:submit="save" class="space-y-6">
        <x-admin.book-form :form="$form" :leaders="$this->leaders" :speakers="$this->speakers" />
        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <flux:button :href="route('books.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">{{ __('Save as draft') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save">{{ __('Update book') }}</flux:button>
        </div>
    </form>
</div>
