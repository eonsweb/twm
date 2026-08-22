<?php

use App\Models\Event;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function event(): ?Event
    {
        return Event::query()->active()->published()->upcoming()->featured()->with(['eventType:id,name,icon,color', 'featuredImage:id,disk,path,media_type,visibility,status'])->orderBy('sort_order')->orderBy('starts_at')->first();
    }
};
?>

<div>
@if ($this->event)
    <section aria-label="{{ __('Featured event') }}" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid overflow-hidden rounded-3xl bg-church-maroon-950 text-white shadow-2xl lg:grid-cols-2">
            <div class="p-8 sm:p-12">
                <p class="text-sm font-bold uppercase tracking-widest text-church-gold-400">{{ __('Featured event') }}</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ $this->event->title }}</h2>
                <p class="mt-4 text-lg text-white/75">{{ $this->event->formattedDateRange() }}</p>
                <p class="mt-2 text-white/75">{{ $this->event->locationLabel() }}</p>
                @if ($this->event->short_description)<p class="mt-6 leading-7 text-white/80">{{ $this->event->short_description }}</p>@endif
                <a href="{{ route('public.events.show', $this->event) }}" class="mt-8 inline-flex items-center gap-2 rounded-xl bg-church-gold-500 px-5 py-3 font-bold text-church-maroon-950 hover:bg-church-gold-400" wire:navigate>{{ __('View featured event') }}<flux:icon.arrow-right class="size-4" /></a>
            </div>
            <div class="min-h-72 bg-church-maroon-900">
                @if ($this->event->imageUrl())<img src="{{ $this->event->imageUrl() }}" alt="{{ $this->event->title }}" class="h-full w-full object-cover">@else<div class="flex h-full min-h-72 items-center justify-center text-church-gold-400"><flux:icon :name="$this->event->effectiveIcon()" class="size-20" /></div>@endif
            </div>
        </div>
    </section>
@endif
</div>
