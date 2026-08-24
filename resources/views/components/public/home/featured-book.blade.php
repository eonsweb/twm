@props(['book' => null, 'section' => null])

@if ($book)
    @php
        $coverUrl = $book->coverUrl();
        $audio = $book->audioSample;
        $audioUrl = $book->audioSampleUrl();
        $purchaseUrl = $book->purchase_url;
        $purchaseUrlIsExternal = $book->isPurchaseUrlExternal();
        $headingId = 'featured-book-'.$book->id.'-heading';
    @endphp

    <section data-featured-book {{ $attributes->class('bg-stone-50 py-16 sm:py-20 lg:py-24') }} aria-labelledby="{{ $headingId }}">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm font-bold uppercase tracking-[0.2em] text-church-gold-700">{{ $section?->heading ?: __('Featured Book') }}</p>

            <div class="mt-8 grid items-center gap-10 overflow-hidden rounded-3xl border border-stone-200 bg-white p-6 shadow-xl sm:p-8 lg:grid-cols-[minmax(16rem,0.75fr)_minmax(0,1.25fr)] lg:gap-14 lg:p-12">
                <div class="mx-auto w-full max-w-xs lg:max-w-sm">
                    @if ($coverUrl)
                        <img src="{{ $coverUrl }}" alt="{{ $book->cover?->alt_text ?: __('Cover of :title', ['title' => $book->title]) }}" class="aspect-[2/3] w-full rounded-2xl object-contain shadow-2xl" loading="lazy">
                    @else
                        <div class="flex aspect-[2/3] items-center justify-center rounded-2xl bg-church-green-950 p-8 text-center shadow-2xl">
                            <span class="font-heading text-2xl font-bold text-church-gold-300">{{ $book->title }}</span>
                        </div>
                    @endif
                </div>

                <div class="min-w-0">
                    <h2 id="{{ $headingId }}" class="font-heading text-3xl font-bold tracking-tight text-church-maroon-950 sm:text-4xl lg:text-5xl">{{ $book->title }}</h2>
                    <p class="mt-3 text-sm font-semibold uppercase tracking-[0.12em] text-church-green-800">{{ __('By :author', ['author' => $book->author_name]) }}</p>

                    @if ($book->short_description)
                        <p class="mt-6 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">{{ $book->short_description }}</p>
                    @endif

                    @if ($purchaseUrl || $audioUrl)
                        <div class="mt-8" @if ($audioUrl) x-data="{ showAudio: false }" @endif>
                            <div data-featured-book-actions class="flex flex-col gap-3 sm:flex-row sm:flex-wrap lg:flex-nowrap lg:items-center">
                                @if ($purchaseUrl)
                                    <a href="{{ $purchaseUrl }}" data-featured-book-purchase @if ($purchaseUrlIsExternal) target="_blank" rel="noopener noreferrer" @else wire:navigate @endif class="inline-flex min-h-12 items-center justify-center rounded-xl bg-church-maroon-900 px-6 py-3 text-sm font-bold uppercase tracking-[0.1em] text-white shadow-sm transition hover:bg-church-maroon-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-church-maroon-900">
                                        {{ __('Get Your Copy Now') }}
                                    </a>
                                @endif

                                @if ($audioUrl)
                                    <button
                                        type="button"
                                        x-on:click="showAudio = ! showAudio"
                                        x-bind:aria-expanded="showAudio"
                                        aria-controls="featured-book-{{ $book->id }}-audio"
                                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-church-green-800 px-6 py-3 text-sm font-bold uppercase tracking-[0.1em] text-church-green-900 transition hover:bg-church-green-900 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-church-green-900"
                                    >
                                        <flux:icon.play data-featured-book-audio-icon class="size-4 fill-current" />
                                        {{ __("Book's Audio Sample") }}
                                    </button>
                                @endif
                            </div>

                            @if ($audioUrl)
                                <div id="featured-book-{{ $book->id }}-audio" x-cloak x-show="showAudio" x-transition.opacity class="mt-5 max-w-2xl">
                                    <audio controls preload="metadata" class="block w-full" aria-label="{{ __('Audio sample from :title', ['title' => $book->title]) }}">
                                        <source src="{{ $audioUrl }}" type="{{ $audio->mime_type }}">
                                        {{ __('Your browser does not support audio playback.') }}
                                    </audio>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-8 text-center">
                <a href="{{ route('public.books.index') }}" wire:navigate class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-church-green-800 px-6 py-3 text-sm font-bold uppercase tracking-[0.12em] text-church-green-900 transition hover:bg-church-green-900 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-church-green-900">
                    {{ __('More Books') }}
                    <flux:icon.arrow-right class="size-4" />
                </a>
            </div>
        </div>
    </section>
@endif
