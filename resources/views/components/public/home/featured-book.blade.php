@props(['book' => null])

<section {{ $attributes->class('rounded-lg border border-zinc-200 bg-stone-50 p-6') }} aria-labelledby="featured-book-heading">
    <h2 id="featured-book-heading" class="font-heading text-xl font-bold uppercase tracking-wide text-church-green-800">{{ __('Featured Book') }}</h2>
    @if ($book)
        <div class="mt-5 grid grid-cols-[8rem_1fr] items-center gap-6 sm:grid-cols-[11rem_1fr]">
            <div class="aspect-[2/3] overflow-hidden rounded bg-zinc-900 shadow-xl">
                @if ($book->coverUrl())
                    <img src="{{ $book->coverUrl() }}" alt="{{ $book->cover?->alt_text ?: $book->title }}" class="size-full object-cover" loading="lazy">
                @else
                    <div class="font-heading grid size-full place-items-center p-4 text-center text-lg font-bold uppercase text-church-gold-300">{{ $book->title }}</div>
                @endif
            </div>
            <div>
                <h3 class="font-heading text-2xl font-bold uppercase leading-tight">{{ $book->title }}</h3>
                <p class="mt-2 text-xs font-semibold uppercase">{{ __('By') }} {{ $book->author_name }}</p>
                @if ($book->short_description)<p class="mt-3 line-clamp-3 text-sm text-zinc-600">{{ $book->short_description }}</p>@endif
                <a href="{{ route('public.books.show', $book) }}" wire:navigate class="mt-5 inline-flex rounded-md border border-red-600 px-4 py-2 text-xs font-bold uppercase text-red-700 hover:bg-red-700 hover:text-white">{{ $book->is_free ? __('Get the Book') : __('Order Now') }}</a>
            </div>
        </div>
    @else
        <p class="mt-5 rounded-md bg-white p-6 text-sm text-zinc-600">{{ __('Featured resources will be available here soon.') }}</p>
    @endif
</section>
