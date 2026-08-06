<?php

use App\BookAvailabilityStatus;
use App\BookFormat;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.public'), Title('Books')] class extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $format = '';
    #[Url] public string $pricing = '';
    #[Url] public string $availability = '';
    #[Url] public string $author = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function books()
    {
        return Book::query()
            ->published()
            ->with('cover')
            ->when($this->search !== '', fn (Builder $query): Builder => $query->search($this->search))
            ->when($this->format !== '', fn (Builder $query): Builder => $query->where('format', $this->format))
            ->when($this->pricing !== '', fn (Builder $query): Builder => $query->where('is_free', $this->pricing === 'free'))
            ->when($this->availability !== '', fn (Builder $query): Builder => $query->where('availability_status', $this->availability))
            ->when($this->author !== '', fn (Builder $query): Builder => $query->where('author_name', 'like', '%'.$this->author.'%'))
            ->latest('published_at')
            ->paginate(12);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'format', 'pricing', 'availability', 'author']);
        $this->resetPage();
    }
};
?>

<main>
    <section class="bg-church-maroon-950 text-white">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <p class="font-semibold text-church-gold-400">{{ __('Resources for growth') }}</p>
            <h1 class="mt-2 text-4xl font-bold tracking-tight sm:text-5xl">{{ __('Books') }}</h1>
            <p class="mt-4 max-w-3xl text-lg text-white/75">{{ __('Explore books from our ministry, leaders, speakers, and guest authors.') }}</p>
        </div>
    </section>
    <div class="mx-auto max-w-7xl space-y-10 px-4 py-14 sm:px-6 lg:px-8">
        <livewire:featured-book />
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :label="__('Search books')" class="xl:col-span-2" />
                <flux:select wire:model.live="format" :label="__('Format')"><flux:select.option value="">{{ __('All formats') }}</flux:select.option>@foreach (BookFormat::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="pricing" :label="__('Price')"><flux:select.option value="">{{ __('Free & paid') }}</flux:select.option><flux:select.option value="free">{{ __('Free') }}</flux:select.option><flux:select.option value="paid">{{ __('Paid') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="availability" :label="__('Availability')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach (BookAvailabilityStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                <div class="flex items-end"><flux:button type="button" variant="ghost" icon="x-mark" wire:click="clearFilters">{{ __('Clear') }}</flux:button></div>
            </div>
            <flux:input wire:model.live.debounce.350ms="author" :label="__('Filter by author')" class="mt-4 max-w-md" />
        </section>
        <section class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse ($this->books as $book)
                <a href="{{ route('public.books.show', $book) }}" wire:navigate wire:key="public-book-{{ $book->id }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                    @if ($book->coverUrl())<img src="{{ $book->coverUrl() }}" alt="{{ __('Cover of :title', ['title' => $book->title]) }}" class="aspect-[2/3] w-full object-cover">@else<div class="flex aspect-[2/3] items-center justify-center bg-slate-100 dark:bg-zinc-800"><flux:icon.book-open class="size-12 text-slate-400" /></div>@endif
                    <div class="p-5"><div class="flex flex-wrap gap-2"><flux:badge>{{ $book->format->label() }}</flux:badge><flux:badge :color="$book->availability_status->color()">{{ $book->availability_status->label() }}</flux:badge></div><h2 class="mt-3 text-xl font-bold group-hover:text-church-maroon-700 dark:group-hover:text-church-gold-400">{{ $book->title }}</h2><p class="mt-1 text-sm text-slate-500">{{ __('By :author', ['author' => $book->author_name]) }}</p><p class="mt-3 line-clamp-3 text-sm text-slate-600 dark:text-zinc-300">{{ $book->short_description }}</p><p class="mt-4 font-bold">{{ $book->displayPrice() }}</p></div>
                </a>
            @empty
                <div class="sm:col-span-2 lg:col-span-3 xl:col-span-4"><x-admin.empty-state icon="book-open" :title="__('No books found')" :description="__('Try clearing one or more filters.')" /></div>
            @endforelse
        </section>
        @if ($this->books->hasPages())<div>{{ $this->books->links() }}</div>@endif
    </div>
</main>
