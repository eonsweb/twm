<?php
use App\Models\Page;
use App\Pages\SectionDataResolver;
use App\PageStatus;
use App\PageVisibility;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.public')] class extends Component {
    public Page $page; public array $sectionData=[]; public string $templateComponent='public.page-templates.default';
    public function mount(Page $page,SectionDataResolver $resolver): void { abort_unless($page->status===PageStatus::Published && $page->published_at?->lte(now()) && ($page->visibility===PageVisibility::Public || ($page->visibility===PageVisibility::Authenticated && auth()->check())),404); $this->page=$page->load(['parent:id,title','sections'=>fn($q)=>$q->where('is_visible',true)->with('backgroundImage'),'featuredImage','ogImage']); $this->templateComponent=match($page->template->value){'homepage'=>'public.page-templates.homepage','full-width'=>'public.page-templates.full-width','sidebar'=>'public.page-templates.sidebar','landing-page'=>'public.page-templates.landing-page','module-index'=>'public.page-templates.module-index','contact'=>'public.page-templates.contact','legal'=>'public.page-templates.legal',default=>'public.page-templates.default'}; foreach($this->page->sections as $section){$this->sectionData[$section->id]=$resolver->resolve($section);} }
}; ?>
@push('meta')<meta name="description" content="{{ $page->meta_description ?: $page->excerpt }}"><meta name="robots" content="{{ $page->robots_index?'index':'noindex' }},{{ $page->robots_follow?'follow':'nofollow' }}"><link rel="canonical" href="{{ $page->canonical_url ?: url('/'.$page->slug) }}"><meta property="og:title" content="{{ $page->og_title ?: $page->meta_title ?: $page->title }}"><meta property="og:description" content="{{ $page->og_description ?: $page->meta_description ?: $page->excerpt }}">@if($page->ogImage?->publicUrl())<meta property="og:image" content="{{ $page->ogImage->publicUrl() }}">@endif@endpush
<x-dynamic-component :component="$templateComponent" :page="$page" :section-data="$sectionData" />
