@props(['section', 'data' => []])
@php($settings = $section->settings ?? [])
<section class="py-12 sm:py-16" aria-labelledby="section-{{ $section->id }}-heading">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if($section->subheading)<p class="text-sm font-bold uppercase tracking-[0.16em] text-church-gold-700">{{ $section->subheading }}</p>@endif
        @if($section->heading)<h2 id="section-{{ $section->id }}-heading" class="mt-2 text-3xl font-bold tracking-tight text-church-maroon-950 sm:text-4xl">{{ $section->heading }}</h2>@endif
        @if($section->content)<div class="prose mt-5 max-w-none text-slate-700">{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($section->content) !!}</div>@endif

        @switch($section->section_type->value)
            @case('hero')
                <div class="mt-8 flex flex-wrap gap-3">@if(data_get($settings,'primary_label') && data_get($settings,'primary_url'))<a href="{{ data_get($settings,'primary_url') }}" class="rounded-lg bg-church-maroon-900 px-5 py-3 font-semibold text-white">{{ data_get($settings,'primary_label') }}</a>@endif @if(data_get($settings,'secondary_label') && data_get($settings,'secondary_url'))<a href="{{ data_get($settings,'secondary_url') }}" class="rounded-lg border border-church-maroon-900 px-5 py-3 font-semibold text-church-maroon-900">{{ data_get($settings,'secondary_label') }}</a>@endif</div>
                @break
            @case('featured-sermons') @case('upcoming-events') @case('latest-posts') @case('ministries-grid') @case('leadership-grid') @case('books-grid')
                <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@forelse(($data['items'] ?? []) as $item)<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-lg font-bold text-church-maroon-950">{{ $item->title ?? $item->name ?? $item->full_name }}</h3><p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $item->excerpt ?? $item->short_description ?? $item->summary ?? $item->description }}</p></article>@empty<p class="text-slate-500">{{ __('Nothing to show yet.') }}</p>@endforelse</div>
                @break
            @case('service-times')
                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach(($data['items'] ?? []) as $item)<div class="rounded-2xl bg-church-green-900 p-6 text-white"><h3 class="font-bold">{{ $item->name }}</h3><p class="mt-2">{{ str($item->day_of_week)->headline() }} · {{ $item->start_time }}</p><p class="text-sm text-white/75">{{ $item->location }}</p></div>@endforeach</div>
                @break
            @case('church-locations') @case('contact-details')
                <div class="mt-8 grid gap-4 sm:grid-cols-2"><div class="rounded-2xl border border-slate-200 p-6"><h3 class="font-bold text-church-maroon-950">{{ data_get($data,'settings.church.official_name',config('app.name')) }}</h3><p class="mt-2 text-slate-600">{{ data_get($data,'settings.contact.physical_address') }}</p></div><div class="rounded-2xl border border-slate-200 p-6"><p><a class="font-semibold text-church-maroon-800" href="mailto:{{ data_get($data,'settings.contact.primary_email') }}">{{ data_get($data,'settings.contact.primary_email') }}</a></p><p class="mt-2">{{ data_get($data,'settings.contact.primary_phone') }}</p></div></div>
                @break
            @case('livestream')
                @if(data_get($data,'settings.social.livestream_enabled') && data_get($data,'settings.social.livestream_url'))<div class="mt-8"><a href="{{ data_get($data,'settings.social.livestream_url') }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg bg-red-700 px-5 py-3 font-bold text-white">{{ __('Watch livestream') }}</a></div>@endif
                @break
            @case('donation-callout')<div class="mt-8"><a href="{{ route('public.give') }}" class="inline-flex rounded-lg bg-church-gold-500 px-5 py-3 font-bold text-church-maroon-950">{{ data_get($settings,'button_label',__('Give now')) }}</a></div>@break
            @case('prayer-request-callout')<div class="mt-8"><a href="{{ route('prayer-requests.create.public') }}" class="inline-flex rounded-lg bg-church-maroon-900 px-5 py-3 font-bold text-white">{{ data_get($settings,'button_label',__('Submit a prayer request')) }}</a></div>@break
        @endswitch
    </div>
</section>
