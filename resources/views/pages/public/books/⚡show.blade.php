<?php

use App\Models\Book;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.public')] class extends Component
{
    public Book $book;

    public function mount(Book $book): void
    {
        abort_unless($book->isPubliclyVisible(), 404);
        $this->book = $book->load(['cover', 'leadership', 'speaker']);
    }

    public function title(): string
    {
        return $this->book->title;
    }
};
?>

<div>
    <section class="bg-church-maroon-950 text-white">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 pb-16 pt-28 sm:px-6 sm:pt-32 lg:grid-cols-[20rem_minmax(0,1fr)] lg:px-8 lg:pt-36">
            @if ($book->coverUrl())<img src="{{ $book->coverUrl() }}" alt="{{ __('Cover of :title', ['title' => $book->title]) }}" class="w-full rounded-2xl object-cover shadow-2xl">@else<div class="flex aspect-[2/3] items-center justify-center rounded-2xl bg-white/10"><flux:icon.book-open class="size-16 text-white/60" /></div>@endif
            <div class="self-center"><div class="flex flex-wrap gap-2"><flux:badge color="amber">{{ $book->format->label() }}</flux:badge><flux:badge :color="$book->availability_status->color()">{{ $book->availability_status->label() }}</flux:badge></div><h1 class="mt-5 text-4xl font-bold tracking-tight sm:text-5xl">{{ $book->title }}</h1>@if ($book->subtitle)<p class="mt-3 text-2xl text-white/80">{{ $book->subtitle }}</p>@endif<p class="mt-5 text-lg text-white/75">{{ __('By :author', ['author' => $book->author_name]) }}</p><p class="mt-6 max-w-3xl text-lg leading-8 text-white/80">{{ $book->short_description }}</p><div class="mt-8 flex flex-wrap items-center gap-4"><span class="text-2xl font-bold">{{ $book->displayPrice() }}</span>@if ($book->purchase_url && $book->availability_status->canPurchase()) @if ($book->isPurchaseUrlExternal())<flux:button :href="$book->purchase_url" target="_blank" rel="noopener noreferrer" variant="primary" icon="shopping-bag">{{ __('Purchase book') }}</flux:button>@else<flux:button :href="$book->purchase_url" wire:navigate variant="primary" icon="shopping-bag">{{ __('Purchase book') }}</flux:button>@endif @endif @if ($book->download_url)<flux:button :href="$book->download_url" target="_blank" rel="noopener noreferrer" icon="arrow-down-tray">{{ $book->is_free ? __('Download free') : __('Download') }}</flux:button>@endif</div></div>
        </div>
    </section>
    <div class="mx-auto grid max-w-7xl gap-10 bg-white px-4 py-14 text-zinc-950 sm:px-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)] lg:px-8">
        <article>@if ($book->description)<h2 class="text-2xl font-bold">{{ __('About this book') }}</h2><p class="mt-4 whitespace-pre-line text-lg leading-8 text-slate-600 dark:text-zinc-300">{{ $book->description }}</p>@endif</article>
        <aside class="rounded-2xl border border-slate-200 p-6 dark:border-zinc-800"><h2 class="text-lg font-bold">{{ __('Book details') }}</h2><dl class="mt-5 space-y-4">@foreach ([__('Publisher') => $book->publisher, __('ISBN') => $book->isbn, __('Edition') => $book->edition, __('Language') => $book->language, __('Pages') => $book->page_count, __('Published') => $book->publication_date?->format('F j, Y'), __('Format') => $book->format->label()] as $label => $value)@if (filled($value))<div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>@endif @endforeach</dl></aside>
    </div>
</div>
