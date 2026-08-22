@props(['leader' => null, 'settings' => [], 'section' => null])

@php
    $homepage = $settings['homepage'] ?? [];
    $sectionSettings = $section?->settings ?? [];
    $leaderTitle = $leader?->primaryLeadershipAssignment?->display_title
        ?: $leader?->primaryLeadershipAssignment?->position?->name;
    $leaderSignature = $leader
        ? collect([$leader->first_name, $leader->middle_name, $leader->last_name])->filter()->implode(' ')
        : null;
    $welcomeHeading = $section?->heading ?: ($homepage['welcome_heading'] ?? null);
    $welcomeMessage = $section?->content ?: ($homepage['welcome_message'] ?? $homepage['welcome_body'] ?? null);
    $welcomeMessageHasHtml = is_string($welcomeMessage) && $welcomeMessage !== strip_tags($welcomeMessage);
    $signature = data_get($sectionSettings, 'welcome_signature')
        ?: data_get($sectionSettings, 'signature_text')
        ?: data_get($homepage, 'welcome_signature')
        ?: $leaderSignature;
    $pastorName = data_get($sectionSettings, 'welcome_pastor_name')
        ?: data_get($sectionSettings, 'pastor_name')
        ?: data_get($homepage, 'welcome_pastor_name')
        ?: $leader?->full_name;
    $pastorRole = data_get($sectionSettings, 'welcome_pastor_role')
        ?: data_get($sectionSettings, 'pastor_title')
        ?: data_get($homepage, 'welcome_pastor_role')
        ?: data_get($homepage, 'welcome_pastor_title')
        ?: $leaderTitle;
    $pastorImageUrl = $section?->backgroundImage?->publicImageUrl() ?: $leader?->photoUrl();
    $pastorImageAlt = data_get($sectionSettings, 'pastor_image_alt')
        ?: $section?->backgroundImage?->alt_text
        ?: ($pastorName ? __('Portrait of :name', ['name' => $pastorName]) : __('Church pastor'));
    $hasWelcomeContent = collect([$welcomeHeading, $welcomeMessage, $signature, $pastorName, $pastorRole, $pastorImageUrl])->contains(fn (mixed $value): bool => filled($value));
@endphp

@if($hasWelcomeContent)
<section id="about" @if($welcomeHeading) aria-labelledby="welcome-heading" @else aria-label="{{ __('Welcome') }}" @endif class="bg-stone-50">
    <div class="mx-auto max-w-7xl overflow-hidden">
        <div class="grid lg:grid-cols-[minmax(0,0.47fr)_minmax(0,0.53fr)]">
            @if($pastorImageUrl)
                <div class="relative min-h-80 overflow-hidden bg-stone-200 sm:min-h-[30rem] lg:min-h-[36rem]">
                    <img src="{{ $pastorImageUrl }}" alt="{{ $pastorImageAlt }}" class="absolute inset-0 size-full object-cover object-top" loading="lazy">
                </div>
            @endif
            <div @class(['flex flex-col justify-center px-6 py-10 sm:px-10 sm:py-14 lg:px-14', 'lg:col-span-2' => ! $pastorImageUrl])>
                @if($welcomeHeading)
                    <h2 id="welcome-heading" class="font-heading text-3xl font-bold tracking-tight text-church-maroon-950 sm:text-4xl lg:text-5xl">{{ $welcomeHeading }}</h2>
                @endif

                @if($welcomeMessageHasHtml)
                    <div class="prose prose-zinc mt-6 max-w-none font-body leading-8 [&_p]:my-3">
                        {!! app(\App\Blog\HtmlSanitizer::class)->sanitize($welcomeMessage) !!}
                    </div>
                @elseif(filled($welcomeMessage))
                    <p class="mt-6 whitespace-pre-line font-body leading-8 text-zinc-700">{{ $welcomeMessage }}</p>
                @endif

                @if($signature)
                    <p class="mt-7 font-signature text-3xl leading-none text-church-gold-700 sm:text-4xl">{{ $signature }}</p>
                @endif
                @if($pastorName)
                    <p class="mt-5 font-heading text-sm font-bold uppercase tracking-[0.08em] text-zinc-900">{{ $pastorName }}</p>
                @endif
                @if($pastorRole)
                    <p class="mt-1 font-body text-xs font-semibold uppercase tracking-[0.08em] text-zinc-600">{{ $pastorRole }}</p>
                @endif
            </div>
        </div>
    </div>
</section>
@endif
