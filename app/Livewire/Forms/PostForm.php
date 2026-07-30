<?php

namespace App\Livewire\Forms;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\User;
use App\PostStatus;
use App\PostVisibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PostForm extends Form
{
    public ?int $postId = null;

    public int|string|null $authorId = null;

    public int|string|null $categoryId = null;

    public string $title = '';

    public string $slug = '';

    public bool $slugManuallyEdited = false;

    public string $excerpt = '';

    public string $content = '';

    public mixed $featuredImage = null;

    public bool $removeFeaturedImage = false;

    public string $featuredImageAltText = '';

    public string $status = 'draft';

    public string $visibility = 'public';

    public bool $isFeatured = false;

    public bool $allowComments = false;

    public string $publishedAt = '';

    public string $scheduledFor = '';

    public string $metaTitle = '';

    public string $metaDescription = '';

    public string $canonicalUrl = '';

    /** @var array<int, int|string> */
    public array $tagIds = [];

    public function setPost(Post $post): void
    {
        $post->loadMissing('tags:id');
        $this->postId = $post->id;
        $this->authorId = $post->author_id;
        $this->categoryId = $post->post_category_id;
        $this->title = $post->title;
        $this->slug = $post->slug;
        $this->slugManuallyEdited = true;
        $this->excerpt = $post->excerpt ?? '';
        $this->content = $post->content ?? '';
        $this->featuredImageAltText = $post->featured_image_alt_text ?? '';
        $this->status = $post->status->value;
        $this->visibility = $post->visibility->value;
        $this->isFeatured = $post->is_featured;
        $this->allowComments = $post->allow_comments;
        $this->publishedAt = $post->published_at?->format('Y-m-d\TH:i') ?? '';
        $this->scheduledFor = $post->scheduled_for?->format('Y-m-d\TH:i') ?? '';
        $this->metaTitle = $post->meta_title ?? '';
        $this->metaDescription = $post->meta_description ?? '';
        $this->canonicalUrl = $post->canonical_url ?? '';
        $this->tagIds = array_values($post->tags->modelKeys());
    }

    public function updatedTitle(string $title): void
    {
        if (! $this->slugManuallyEdited && $this->postId === null) {
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
        $this->slug = Str::slug($this->slug !== '' ? $this->slug : $this->title);
        $this->excerpt = trim($this->excerpt);
        $this->featuredImageAltText = Str::squish($this->featuredImageAltText);
        $this->metaTitle = Str::squish($this->metaTitle);
        $this->metaDescription = Str::squish($this->metaDescription);
        $this->authorId = filled($this->authorId) ? (int) $this->authorId : null;
        $this->categoryId = filled($this->categoryId) ? (int) $this->categoryId : null;
        $this->tagIds = array_values(collect($this->tagIds)->map(fn (int|string $id): int => (int) $id)->unique()->all());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $requiresContent = in_array($this->status, [PostStatus::Published->value, PostStatus::Scheduled->value], true);

        return [
            'authorId' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'categoryId' => ['nullable', 'integer', Rule::exists(PostCategory::class, 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:255', Rule::unique(Post::class, 'slug')->ignore($this->postId)],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => [Rule::requiredIf($requiresContent), 'nullable', 'string', 'max:500000'],
            'featuredImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'removeFeaturedImage' => ['boolean'],
            'featuredImageAltText' => [Rule::requiredIf($this->featuredImage !== null), 'nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'visibility' => ['required', Rule::enum(PostVisibility::class)],
            'isFeatured' => ['boolean'],
            'allowComments' => ['boolean'],
            'publishedAt' => [Rule::requiredIf($this->status === PostStatus::Published->value), 'nullable', 'date'],
            'scheduledFor' => [
                Rule::requiredIf($this->status === PostStatus::Scheduled->value),
                'nullable', 'date',
                Rule::when($this->status === PostStatus::Scheduled->value && $this->postId === null, ['after:now']),
            ],
            'metaTitle' => ['nullable', 'string', 'max:70'],
            'metaDescription' => ['nullable', 'string', 'max:170'],
            'canonicalUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'tagIds' => ['array', 'max:30'],
            'tagIds.*' => ['integer', 'distinct', Rule::exists(Tag::class, 'id')],
        ];
    }

    /** @return array<string, mixed> */
    public function postData(): array
    {
        return collect([
            'author_id' => $this->authorId,
            'post_category_id' => $this->categoryId,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'featured_image_alt_text' => $this->featuredImageAltText,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'is_featured' => $this->isFeatured,
            'allow_comments' => $this->allowComments,
            'published_at' => $this->dateTime($this->publishedAt),
            'scheduled_for' => $this->dateTime($this->scheduledFor),
            'archived_at' => $this->status === PostStatus::Archived->value ? now() : null,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'canonical_url' => $this->canonicalUrl,
        ])->map(fn (mixed $value): mixed => $value === '' ? null : $value)->all();
    }

    private function dateTime(string $value): ?Carbon
    {
        return $value === '' ? null : Carbon::parse($value, config('app.timezone'))->utc();
    }
}
