@props(['testimonials' => []])

<section {{ $attributes->class('rounded-lg border border-zinc-200 bg-white p-6') }} aria-labelledby="testimonials-heading" @if (count($testimonials) > 0) x-data="{ current: 0, total: {{ count($testimonials) }} }" x-on:keydown.left="current = (current - 1 + total) % total" x-on:keydown.right="current = (current + 1) % total" tabindex="0" @endif>
    <h2 id="testimonials-heading" class="font-heading text-xl font-bold uppercase tracking-wide text-church-green-800">{{ __('Testimonies') }}</h2>
    @if (count($testimonials) > 0)
        <div class="relative mt-5 min-h-52">
            @foreach ($testimonials as $index => $testimonial)
                <figure x-cloak x-show="current === {{ $index }}" x-transition.opacity class="grid items-center gap-6 sm:grid-cols-[1fr_11rem]">
                    <blockquote class="relative pl-8 text-base leading-7 text-zinc-700 before:absolute before:left-0 before:top-0 before:text-5xl before:font-bold before:text-church-gold-500 before:content-['“']">
                        <p>{{ $testimonial['quote'] }}</p>
                        <figcaption class="mt-5 text-sm font-bold text-zinc-950">&ndash; {{ $testimonial['name'] }}@if ($testimonial['role']), <span class="font-normal text-zinc-500">{{ $testimonial['role'] }}</span>@endif</figcaption>
                    </blockquote>
                    <div class="mx-auto grid size-40 place-items-center overflow-hidden rounded-full bg-stone-100 text-4xl font-bold text-church-maroon-900/30">
                        @if ($testimonial['portrait_url'])
                            <img src="{{ $testimonial['portrait_url'] }}" alt="" class="size-full object-cover" loading="lazy">
                        @else
                            {{ mb_substr($testimonial['name'], 0, 1) }}
                        @endif
                    </div>
                </figure>
            @endforeach
        </div>
        @if (count($testimonials) > 1)
            <div class="mt-3 flex items-center justify-center gap-3">
                <button type="button" x-on:click="current = (current - 1 + total) % total" class="grid size-9 place-items-center rounded-full border border-zinc-300 hover:bg-stone-100" aria-label="{{ __('Previous testimony') }}"><flux:icon.chevron-left class="size-4" /></button>
                <template x-for="index in total" :key="index"><button type="button" x-on:click="current = index - 1" class="size-2 rounded-full" x-bind:class="current === index - 1 ? 'bg-church-green-700' : 'bg-zinc-300'" x-bind:aria-label="`{{ __('Show testimony') }} ${index}`"></button></template>
                <button type="button" x-on:click="current = (current + 1) % total" class="grid size-9 place-items-center rounded-full border border-zinc-300 hover:bg-stone-100" aria-label="{{ __('Next testimony') }}"><flux:icon.chevron-right class="size-4" /></button>
            </div>
        @endif
    @else
        <div class="mt-5 grid min-h-52 place-items-center rounded-md bg-stone-50 p-8 text-center text-sm text-zinc-600">{{ __('Stories of God’s faithfulness will be shared here soon.') }}</div>
    @endif
</section>
