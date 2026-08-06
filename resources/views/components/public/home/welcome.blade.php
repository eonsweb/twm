@props(['leader' => null, 'settings' => []])

@php
    $homepage = $settings['homepage'] ?? [];
    $church = $settings['church'] ?? [];
    $leaderTitle = $leader?->primaryLeadershipAssignment?->display_title
        ?: $leader?->primaryLeadershipAssignment?->position?->name;
@endphp

<section id="about" aria-labelledby="welcome-heading" class="bg-stone-50">
    <div class="mx-auto max-w-7xl">
        <div class="grid min-h-96 sm:grid-cols-2">
            <div class="relative min-h-72 overflow-hidden bg-gradient-to-br from-stone-200 to-stone-100">
                @if ($leader?->photoUrl())
                    <img src="{{ $leader->photoUrl() }}" alt="{{ $leader->full_name }}" class="absolute inset-0 size-full object-cover object-top" loading="lazy">
                @else
                    <div class="absolute inset-0 grid place-items-center text-7xl font-bold text-church-maroon-900/20">{{ $leader ? mb_substr($leader->first_name, 0, 1).mb_substr($leader->last_name, 0, 1) : 'TWM' }}</div>
                @endif
            </div>
            <div class="flex flex-col justify-center p-8 lg:p-10">
                <h2 id="welcome-heading" class="font-signature text-4xl text-church-gold-600">{{ $homepage['welcome_heading'] ?? __('Welcome Home!') }}</h2>
                <p class="mt-5 text-sm leading-7 text-zinc-700">{{ $homepage['welcome_body'] ?? '' }}</p>
                @if ($leader)
                    <p class="font-signature mt-6 text-2xl text-zinc-900">{{ $leader->full_name }}</p>
                    <p class="mt-1 text-xs font-bold uppercase tracking-wide text-zinc-600">{{ $leaderTitle ?: ($church['lead_pastor_name'] ?? __('Church Leadership')) }}</p>
                @endif
            </div>
        </div>

    </div>
</section>
