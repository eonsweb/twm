@props(['settings' => []])

@php $homepage = $settings['homepage'] ?? []; @endphp

<section aria-label="{{ __('Prayer and giving') }}">
    <div class="grid lg:grid-cols-2">
        <article class="relative isolate overflow-hidden bg-gradient-to-r from-red-950 to-red-700 px-6 py-9 text-white sm:px-10 lg:pl-[max(2.5rem,calc((100vw-80rem)/2))]">
            <div class="absolute -left-16 top-1/2 -z-10 size-48 -translate-y-1/2 rounded-full bg-red-500/25 blur-2xl"></div>
            <h2 class="font-heading text-2xl font-bold uppercase">{{ $homepage['prayer_heading'] ?? __('Need Prayer?') }}</h2>
            <p class="mt-1 text-sm text-red-100">{{ $homepage['prayer_body'] ?? '' }}</p>
            <a href="{{ route('prayer-requests.create.public') }}" wire:navigate class="mt-5 inline-flex rounded-md bg-church-gold-400 px-5 py-2.5 text-xs font-bold uppercase text-zinc-950 hover:bg-church-gold-300">{{ __('Submit Prayer Request') }}</a>
        </article>
        <article class="relative isolate overflow-hidden bg-gradient-to-r from-church-gold-600 to-amber-400 px-6 py-9 text-center text-zinc-950 sm:px-10">
            <div class="absolute -right-16 top-1/2 -z-10 size-48 -translate-y-1/2 rounded-full bg-white/20 blur-2xl"></div>
            <h2 class="font-heading text-2xl font-bold uppercase">{{ $homepage['giving_heading'] ?? __('Partner With the Work of God') }}</h2>
            <p class="mt-1 text-sm">{{ $homepage['giving_body'] ?? '' }}</p>
            <a href="{{ route('public.give') }}" wire:navigate class="mt-5 inline-flex rounded-md bg-church-green-700 px-5 py-2.5 text-xs font-bold uppercase text-white hover:bg-church-green-800">{{ __('Give Online') }}</a>
        </article>
    </div>
</section>
