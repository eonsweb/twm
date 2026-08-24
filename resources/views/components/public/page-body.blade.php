@props(['page', 'sectionData' => [], 'contentWidth' => 'max-w-4xl'])
<article class="min-h-[60vh]">
    <header class="bg-church-maroon-950 text-white"><div class="mx-auto max-w-7xl px-4 pb-16 pt-28 sm:px-6 sm:pb-20 sm:pt-32 lg:px-8 lg:pt-36"><h1 class="text-4xl font-bold tracking-tight sm:text-6xl">{{ $page->title }}</h1>@if($page->excerpt)<p class="mt-5 max-w-3xl text-lg text-white/80">{{ $page->excerpt }}</p>@endif</div></header>
    @if($page->content && trim(strip_tags($page->content)) !== 'Content for this page can be managed from the Pages administration module.')<div @class(['prose mx-auto px-4 py-12 sm:px-6', $contentWidth])>{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($page->content) !!}</div>@endif
    @foreach($page->sections as $section)<x-public.page-section :section="$section" :data="$sectionData[$section->id] ?? []" wire:key="public-section-{{ $section->id }}" />@endforeach
</article>
