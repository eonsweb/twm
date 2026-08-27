@props(['settings' => [], 'mediaUrls' => []])

@php
    $general = $settings['general'] ?? [];
    $church = $settings['church'] ?? [];
    $contact = $settings['contact'] ?? [];
    $social = $settings['social'] ?? [];
    $footerLogoUrl = ($mediaUrls['footer_logo'] ?? null) ?: ($mediaUrls['primary_logo'] ?? null);
    $socialNames = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'x' => 'X', 'tiktok' => 'TikTok'];
@endphp

<footer class="bg-zinc-950 text-zinc-300">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
        <section aria-labelledby="footer-about">
            @if ($footerLogoUrl)
                <img src="{{ $footerLogoUrl }}" alt="" class="mb-5 h-16 w-auto object-contain" loading="lazy">
            @endif
            <h2 id="footer-about" class="font-heading text-lg font-bold uppercase text-church-gold-300">{{ __('About Us') }}</h2>
            <p class="mt-3 text-sm leading-6">{{ ($church['mission'] ?? null) ?: ($general['meta_description'] ?? __('A Christ-centered ministry committed to prayer, worship, the Word, and transformed lives.')) }}</p>
            <div class="mt-5 flex flex-wrap gap-3">
                @foreach ($socialNames as $key => $label)
                    @if (($social[$key.'_enabled'] ?? false) && filled($social[$key.'_url'] ?? null))
                        <a href="{{ $social[$key.'_url'] }}" target="_blank" rel="noopener noreferrer" class="text-xs font-bold text-white hover:text-church-gold-300"><span class="sr-only">{{ $label }}</span>{{ mb_substr($label, 0, 2) }}</a>
                    @endif
                @endforeach
            </div>
        </section>

        <section aria-labelledby="footer-links">
            <h2 id="footer-links" class="font-heading text-lg font-bold uppercase text-church-gold-300">{{ __('Quick Links') }}</h2>
            <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <a href="{{ route('home') }}#about" class="hover:text-white">{{ __('About Us') }}</a>
                <a href="{{ route('public.ministries.index') }}" wire:navigate class="hover:text-white">{{ __('Ministries') }}</a>
                <a href="{{ route('public.sermons.index') }}" wire:navigate class="hover:text-white">{{ __('Sermons') }}</a>
                <a href="{{ route('public.books.index') }}" wire:navigate class="hover:text-white">{{ __('Books') }}</a>
                <a href="{{ route('public.events.index') }}" wire:navigate class="hover:text-white">{{ __('Events') }}</a>
                <a href="{{ route('public.give') }}" wire:navigate class="hover:text-white">{{ __('Give') }}</a>
                <a href="{{ route('public.contact') }}" wire:navigate class="hover:text-white">{{ __('Contact') }}</a>
                <a href="{{ route('prayer-requests.create.public') }}" wire:navigate class="hover:text-white">{{ __('Prayer') }}</a>
            </div>
        </section>

        <section aria-labelledby="footer-contact">
            <h2 id="footer-contact" class="font-heading text-lg font-bold uppercase text-church-gold-300">{{ __('Contact Us') }}</h2>
            <address class="mt-3 space-y-2 text-sm not-italic">
                @if (filled($contact['primary_phone'] ?? null))<a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['primary_phone']) }}" class="block hover:text-white">{{ $contact['primary_phone'] }}</a>@endif
                @if (filled($contact['primary_email'] ?? null))<a href="mailto:{{ $contact['primary_email'] }}" class="block break-all hover:text-white">{{ $contact['primary_email'] }}</a>@endif
                @if (filled($contact['physical_address'] ?? null))<p>{{ $contact['physical_address'] }}</p>@endif
            </address>
        </section>

        <section aria-labelledby="footer-find">
            <h2 id="footer-find" class="font-heading text-lg font-bold uppercase text-church-gold-300">{{ __('Find Us') }}</h2>
            <div class="mt-3 rounded-lg border border-white/10 bg-white/5 p-5 text-sm">
                <p>{{ ($church['main_location'] ?? null) ?: (($contact['city'] ?? null) ?: __('Visit us this week')) }}</p>
                @if (filled($contact['map_url'] ?? null))
                    <a href="{{ $contact['map_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex font-bold text-church-gold-300 hover:text-church-gold-100">{{ __('Get directions') }} &rarr;</a>
                @endif
            </div>
        </section>
    </div>
    <div class="border-t border-white/10 px-4 py-5 text-center text-xs text-zinc-500">
        &copy; {{ now()->year }} {{ $general['copyright_text'] ?? ($church['official_name'] ?? config('app.name')) }}. {{ __('All rights reserved.') }}
    </div>
</footer>
