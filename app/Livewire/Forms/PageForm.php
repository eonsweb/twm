<?php

namespace App\Livewire\Forms;

use App\Models\Media;
use App\Models\Page;
use App\PageStatus;
use App\PageTemplate;
use App\PageType;
use App\PageVisibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PageForm extends Form
{
    public ?int $pageId = null;

    public string $title = '';

    public string $slug = '';

    public bool $slugManuallyEdited = false;

    public string $pageType = 'standard';

    public string $template = 'default';

    public string $excerpt = '';

    public string $content = '';

    public int|string|null $featuredImageId = null;

    public string $status = 'draft';

    public string $visibility = 'public';

    public bool $isHomepage = false;

    public bool $showInNavigation = false;

    public string $navigationLabel = '';

    public int|string|null $navigationOrder = null;

    public int|string|null $parentId = null;

    public string $publishedAt = '';

    public string $metaTitle = '';

    public string $metaDescription = '';

    public string $metaKeywords = '';

    public string $canonicalUrl = '';

    public bool $robotsIndex = true;

    public bool $robotsFollow = true;

    public string $ogTitle = '';

    public string $ogDescription = '';

    public int|string|null $ogImageId = null;

    public function setPage(Page $page): void
    {
        $this->pageId = $page->id;
        $this->title = $page->title;
        $this->slug = $page->slug;
        $this->pageType = $page->page_type->value;
        $this->template = $page->template->value;
        $this->excerpt = $page->excerpt ?? '';
        $this->content = $page->content ?? '';
        $this->featuredImageId = $page->featured_image_id;
        $this->status = $page->status->value;
        $this->visibility = $page->visibility->value;
        $this->isHomepage = $page->is_homepage;
        $this->showInNavigation = $page->show_in_navigation;
        $this->navigationLabel = $page->navigation_label ?? '';
        $this->navigationOrder = $page->navigation_order;
        $this->parentId = $page->parent_id;
        $this->publishedAt = $page->published_at?->format('Y-m-d\TH:i') ?? '';
        $this->metaTitle = $page->meta_title ?? '';
        $this->metaDescription = $page->meta_description ?? '';
        $this->metaKeywords = $page->meta_keywords ?? '';
        $this->canonicalUrl = $page->canonical_url ?? '';
        $this->robotsIndex = $page->robots_index;
        $this->robotsFollow = $page->robots_follow;
        $this->ogTitle = $page->og_title ?? '';
        $this->ogDescription = $page->og_description ?? '';
        $this->ogImageId = $page->og_image_id;
        $this->slugManuallyEdited = true;
    }

    public function updatedTitle(string $title): void
    {
        if (! $this->slugManuallyEdited && $this->pageId === null) {
            $this->slug = Str::slug($title);
        }
    }

    public function markSlugAsEdited(): void
    {
        $this->slugManuallyEdited = true;
    }

    public function normalize(): void
    {
        $this->title = Str::squish($this->title);
        $this->slug = Str::slug($this->slug ?: $this->title);
        $this->navigationLabel = Str::squish($this->navigationLabel);
        foreach (['parentId', 'featuredImageId', 'ogImageId', 'navigationOrder'] as $property) {
            $this->{$property} = filled($this->{$property}) ? (int) $this->{$property} : null;
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:255', Rule::notIn(Page::RESERVED_SLUGS), Rule::unique(Page::class, 'slug')->ignore($this->pageId)],
            'pageType' => ['required', Rule::enum(PageType::class)], 'template' => ['required', Rule::enum(PageTemplate::class)],
            'excerpt' => ['nullable', 'string', 'max:2000'], 'content' => ['nullable', 'string', 'max:500000'],
            'featuredImageId' => ['nullable', 'integer', Rule::exists(Media::class, 'id')->where('media_type', 'image')],
            'status' => ['required', Rule::enum(PageStatus::class)], 'visibility' => ['required', Rule::enum(PageVisibility::class)],
            'isHomepage' => ['boolean'], 'showInNavigation' => ['boolean'], 'navigationLabel' => ['nullable', 'string', 'max:255'],
            'navigationOrder' => ['nullable', 'integer', 'min:0'], 'parentId' => ['nullable', 'integer', Rule::exists(Page::class, 'id'), Rule::notIn(array_filter([$this->pageId]))],
            'publishedAt' => [Rule::requiredIf(in_array($this->status, ['published', 'scheduled'], true)), 'nullable', 'date', Rule::when($this->status === 'scheduled', ['after:now'])],
            'metaTitle' => ['nullable', 'string', 'max:255'], 'metaDescription' => ['nullable', 'string', 'max:1000'], 'metaKeywords' => ['nullable', 'string', 'max:1000'],
            'canonicalUrl' => ['nullable', 'url:http,https', 'max:2048'], 'robotsIndex' => ['boolean'], 'robotsFollow' => ['boolean'],
            'ogTitle' => ['nullable', 'string', 'max:255'], 'ogDescription' => ['nullable', 'string', 'max:1000'],
            'ogImageId' => ['nullable', 'integer', Rule::exists(Media::class, 'id')->where('media_type', 'image')],
        ];
    }

    /** @return array<string, mixed> */
    public function pageData(): array
    {
        return collect(['title' => $this->title, 'slug' => $this->slug, 'page_type' => $this->pageType, 'template' => $this->template, 'excerpt' => $this->excerpt, 'content' => $this->content, 'featured_image_id' => $this->featuredImageId, 'status' => $this->status, 'visibility' => $this->visibility, 'is_homepage' => $this->isHomepage, 'show_in_navigation' => $this->showInNavigation, 'navigation_label' => $this->navigationLabel, 'navigation_order' => $this->navigationOrder, 'parent_id' => $this->parentId, 'published_at' => $this->publishedAt === '' ? null : Carbon::parse($this->publishedAt, config('app.timezone'))->utc(), 'meta_title' => $this->metaTitle, 'meta_description' => $this->metaDescription, 'meta_keywords' => $this->metaKeywords, 'canonical_url' => $this->canonicalUrl, 'robots_index' => $this->robotsIndex, 'robots_follow' => $this->robotsFollow, 'og_title' => $this->ogTitle, 'og_description' => $this->ogDescription, 'og_image_id' => $this->ogImageId])->map(fn (mixed $value): mixed => $value === '' ? null : $value)->all();
    }
}
