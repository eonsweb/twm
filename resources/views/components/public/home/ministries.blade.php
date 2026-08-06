@props(['ministries'])

<section aria-labelledby="ministries-heading" class="bg-white py-12">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 id="ministries-heading" class="font-heading text-center text-2xl font-bold uppercase tracking-widest text-church-green-800">{{ __('Our Ministries') }}</h2>
        @if ($ministries->isNotEmpty())
            <div class="mt-7 grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-7">
                @foreach ($ministries as $ministry)
                    <a href="{{ route('public.ministries.show', $ministry) }}" wire:navigate wire:key="ministry-{{ $ministry->id }}" class="group overflow-hidden rounded-md border border-zinc-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                        <div class="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-church-green-900 to-zinc-950">
                            @if ($ministry->imageUrl())
                                <img src="{{ $ministry->imageUrl() }}" alt="" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                            @endif
                            <span class="absolute -bottom-5 left-1/2 grid size-11 -translate-x-1/2 place-items-center rounded-full border-2 border-white bg-red-700 text-sm font-bold text-white">{{ mb_substr($ministry->name, 0, 1) }}</span>
                        </div>
                        <div class="px-2 pb-4 pt-8 text-center">
                            <h3 class="text-xs font-extrabold uppercase leading-4">{{ $ministry->name }}</h3>
                            <span class="mt-3 block text-xs font-bold text-church-gold-600">{{ __('Learn More') }} &rarr;</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="mx-auto mt-7 max-w-2xl rounded-lg border border-zinc-200 bg-stone-50 p-8 text-center text-sm text-zinc-600">{{ __('Our ministry teams are being prepared. We would still love to help you find your place.') }}</div>
        @endif
    </div>
</section>
