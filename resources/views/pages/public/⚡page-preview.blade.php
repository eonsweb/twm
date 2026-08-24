<?php

use App\Models\Page;
use App\Pages\SectionDataResolver;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.public')] class extends Component {
    public Page $page;
    public array $sectionData = [];

    public function mount(Page $page, SectionDataResolver $resolver): void
    {
        Gate::authorize('preview', $page);
        $this->page = $page->load(['sections' => fn ($query) => $query->withTrashed()->with('backgroundImage')]);
        foreach ($this->page->sections as $section) {
            $this->sectionData[$section->id] = $resolver->resolve($section);
        }
    }
};
?>
@push('meta')<meta name="robots" content="noindex,nofollow">@endpush
<div>
    <div class="sticky top-0 z-50 bg-amber-400 px-4 py-3 text-center text-sm font-bold text-amber-950">{{ __('Secure preview — this page is not publicly published.') }}</div>
    <article>
        <header class="bg-church-maroon-950 text-white"><div class="mx-auto max-w-7xl px-4 pb-16 pt-28 sm:pt-32 lg:pt-36"><h1 class="text-4xl font-bold">{{ $page->title }}</h1></div></header>
        @if($page->content)<div class="prose mx-auto max-w-4xl px-4 py-12">{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($page->content) !!}</div>@endif
        @foreach($page->sections as $section)<x-public.page-section :section="$section" :data="$sectionData[$section->id] ?? []" wire:key="preview-section-{{ $section->id }}" />@endforeach
    </article>
</div>
