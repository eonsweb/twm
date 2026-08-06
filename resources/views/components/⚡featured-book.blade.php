<?php

use App\Models\Book;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function book(): ?Book
    {
        return Book::query()->published()->featured()->with('cover')->latest('published_at')->first();
    }
};
?>

<div>
    @if ($this->book)
        <section class="overflow-hidden rounded-3xl bg-church-maroon-950 text-white shadow-xl">
            <div class="grid lg:grid-cols-[16rem_minmax(0,1fr)]">
                @if ($this->book->coverUrl())<img src="{{ $this->book->coverUrl() }}" alt="{{ __('Cover of :title', ['title' => $this->book->title]) }}" class="h-full max-h-[28rem] w-full object-cover">@endif
                <div class="self-center p-8 sm:p-10"><p class="font-semibold uppercase tracking-widest text-church-gold-400">{{ __('Featured book') }}</p><h2 class="mt-3 text-3xl font-bold">{{ $this->book->title }}</h2><p class="mt-2 text-white/70">{{ __('By :author', ['author' => $this->book->author_name]) }}</p><p class="mt-5 max-w-2xl text-lg leading-8 text-white/80">{{ $this->book->short_description }}</p><div class="mt-7 flex items-center gap-4"><span class="font-bold">{{ $this->book->displayPrice() }}</span><flux:button :href="route('public.books.show', $this->book)" variant="primary" wire:navigate>{{ __('View book') }}</flux:button></div></div>
            </div>
        </section>
    @endif
</div>
