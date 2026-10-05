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
<section id="about" @if($welcomeHeading) aria-labelledby="welcome-heading" @else aria-label="{{ __('Welcome') }}" @endif class="relative overflow-hidden bg-[#090909] py-20 text-white lg:py-28">
    <span aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 whitespace-nowrap text-center text-[16vw] font-black uppercase leading-none tracking-tighter text-white/[0.035]">Who we are</span>
    <div class="twm-container relative">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
            @if($pastorImageUrl)
                <div class="relative min-h-80 overflow-hidden bg-white/5 sm:min-h-[30rem] lg:order-2 lg:min-h-[36rem]">
                    <img src="{{ $pastorImageUrl }}" alt="{{ $pastorImageAlt }}" class="absolute inset-0 size-full object-cover object-top" loading="lazy">
                </div>
            @endif
            <div @class(['flex flex-col justify-center py-8', 'lg:col-span-2' => ! $pastorImageUrl])>
                <p class="twm-eyebrow mb-5">{{ __('Who We Are') }}</p>
                @if($welcomeHeading)
                    <h2 id="welcome-heading" class="twm-heading">{{ $welcomeHeading }}</h2>
                @endif

                @if($welcomeMessageHasHtml)
                    <div class="prose prose-invert mt-6 max-w-none font-body leading-8 [&_p]:my-3">
                        {!! app(\App\Blog\HtmlSanitizer::class)->sanitize($welcomeMessage) !!}
                    </div>
                @elseif(filled($welcomeMessage))
                    <p class="mt-6 whitespace-pre-line font-body leading-8 text-white/70">{{ $welcomeMessage }}</p>
                @endif

                @if($signature)
                    <p class="mt-7 font-signature text-3xl leading-none text-church-gold-700 sm:text-4xl">{{ $signature }}</p>
                @endif
                @if($pastorName)
                    <p class="mt-5 font-heading text-sm font-bold uppercase tracking-[0.08em] text-white">{{ $pastorName }}</p>
                @endif
                @if($pastorRole)
                    <p class="mt-1 font-body text-xs font-semibold uppercase tracking-[0.08em] text-white/60">{{ $pastorRole }}</p>
                @endif
                <a href="{{ route('public.contact') }}" wire:navigate class="mt-8 inline-flex items-center gap-4 text-xs font-bold uppercase tracking-widest text-[var(--twm-accent)]">{{ __('Connect with TWM') }} <flux:icon.arrow-up-right class="size-5" /></a>
            </div>
        </div>
    </div>
</section>
@endif
