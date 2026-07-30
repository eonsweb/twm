<?php

use App\Models\Ministry;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.public')] class extends Component
{
    public Ministry $ministry;

    public function mount(Ministry $ministry): void
    {
        abort_unless($ministry->isPubliclyVisible(), 404);
        $this->ministry = $ministry->load([
            'leaders' => fn ($query) => $query->where('is_public', true),
            'sermons' => fn ($query) => $query->publiclyAvailable()->latest('sermon_date')->limit(6),
            'events' => fn ($query) => $query->published()->upcoming()->orderBy('starts_at')->limit(6),
        ]);
    }

    public function title(): string { return $this->ministry->name; }
};
?>

<main>
    <section class="bg-church-maroon-950 text-white"><div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8">@if ($ministry->imageUrl())<img src="{{ $ministry->imageUrl() }}" alt="" class="h-full max-h-[28rem] w-full rounded-2xl object-cover">@endif<div class="self-center">@if ($ministry->logoUrl())<img src="{{ $ministry->logoUrl() }}" alt="" class="mb-6 size-24 rounded-2xl object-cover">@endif<h1 class="text-4xl font-bold tracking-tight sm:text-5xl">{{ $ministry->name }}</h1><p class="mt-5 text-lg text-white/80">{{ $ministry->short_description }}</p></div></div></section>
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-3 lg:px-8">
        <article class="space-y-10 lg:col-span-2">@foreach ([__('About') => $ministry->description, __('Our mission') => $ministry->mission, __('Our vision') => $ministry->vision] as $heading => $content)@if ($content)<section><h2 class="text-2xl font-bold">{{ $heading }}</h2><p class="mt-3 whitespace-pre-line leading-7 text-slate-600 dark:text-zinc-300">{{ $content }}</p></section>@endif @endforeach</article>
        <aside class="space-y-6"><section class="rounded-2xl border border-slate-200 p-6 dark:border-zinc-800"><h2 class="font-bold">{{ __('Meeting information') }}</h2><p class="mt-3">{{ collect([$ministry->meeting_day, $ministry->meeting_time?->format('g:i A')])->filter()->implode(' · ') }}</p><p class="mt-1 text-slate-600 dark:text-zinc-300">{{ $ministry->meeting_location }}</p>@if ($ministry->contact_email)<a class="mt-4 block font-semibold text-church-maroon-700 dark:text-church-gold-400" href="mailto:{{ $ministry->contact_email }}">{{ $ministry->contact_email }}</a>@endif @if ($ministry->contact_phone)<a class="mt-1 block" href="tel:{{ $ministry->contact_phone }}">{{ $ministry->contact_phone }}</a>@endif</section><section class="rounded-2xl border border-slate-200 p-6 dark:border-zinc-800"><h2 class="font-bold">{{ __('Ministry leadership') }}</h2><div class="mt-4 space-y-4">@forelse ($ministry->leaders as $leader)<div><p class="font-semibold">{{ $leader->full_name }}</p><p class="text-sm text-slate-500">{{ $leader->pivot->role_title }}</p></div>@empty<p class="text-slate-500">{{ __('Leadership details coming soon.') }}</p>@endforelse</div></section></aside>
    </div>
</main>
