<?php

namespace App\Actions\Blog;

use App\Activity\ActivityLogger;
use App\Blog\HtmlSanitizer;
use App\Models\Post;
use App\Models\User;
use App\PostStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SavePost
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $tagIds
     */
    public function handle(
        User $actor,
        array $data,
        array $tagIds = [],
        ?UploadedFile $featuredImage = null,
        bool $removeFeaturedImage = false,
        ?Post $post = null,
    ): Post {
        $creating = $post === null;
        Gate::forUser($actor)->authorize($creating ? 'create' : 'update', $post ?? Post::class);
        $post ??= new Post;
        $old = $creating ? [] : $this->audit($post->loadMissing('tags:id'));
        $oldImage = $post->featured_image;
        $newImage = $this->storeImage($featuredImage);
        $data['content'] = $this->htmlSanitizer->sanitize((string) ($data['content'] ?? ''));
        $data['slug'] = Post::uniqueSlug((string) ($data['slug'] ?: $data['title']), $post->exists ? $post->id : null);
        $data['author_id'] ??= $actor->id;

        if (! $creating && (int) $data['author_id'] !== $post->author_id) {
            Gate::forUser($actor)->authorize('manageAuthor', $post);
        } elseif ($creating && (int) $data['author_id'] !== $actor->id) {
            Gate::forUser($actor)->authorize('manageAuthor', $post);
        }
        $this->authorizeWorkflow($actor, $post, $data);

        if ($newImage !== null) {
            $data['featured_image'] = $newImage;
        } elseif ($removeFeaturedImage) {
            $data['featured_image'] = null;
        }

        try {
            $saved = DB::transaction(function () use ($post, $data, $tagIds): Post {
                $post->fill($data)->save();
                $post->tags()->sync($tagIds);

                return $post->refresh()->load(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug']);
            });
        } catch (Throwable $exception) {
            if ($newImage !== null) {
                Storage::disk('public')->delete($newImage);
            }
            throw $exception;
        }

        if (($newImage !== null || $removeFeaturedImage) && $oldImage !== null && $oldImage !== $saved->featured_image) {
            $this->deleteIfUnshared($oldImage, $saved->id);
        }
        $new = $this->audit($saved);
        $keys = $creating ? array_keys($new) : collect($new)->filter(fn (mixed $value, string $key): bool => ($old[$key] ?? null) !== $value)->keys()->all();
        $this->activityLogger->log(
            logName: 'blog',
            event: $creating ? 'post.created' : 'post.updated',
            description: ($creating ? 'Created' : 'Updated')." post \"{$saved->title}\".",
            subject: $saved,
            causer: $actor,
            oldValues: Arr::only($old, $keys),
            newValues: Arr::only($new, $keys),
        );
        $this->logMeaningfulChanges($actor, $saved, $old, $new);

        return $saved;
    }

    /** @param array<string, mixed> $data */
    private function authorizeWorkflow(User $actor, Post $post, array $data): void
    {
        $newStatus = PostStatus::from((string) $data['status']);
        $oldStatus = $post->exists ? $post->status : PostStatus::Draft;
        if ($newStatus !== $oldStatus) {
            Gate::forUser($actor)->authorize(
                $newStatus === PostStatus::Archived ? 'archive' : 'publish',
                $post,
            );
        }
    }

    private function storeImage(?UploadedFile $image): ?string
    {
        if ($image === null) {
            return null;
        }
        $path = $image->store('blog/featured', 'public');
        throw_if($path === false, RuntimeException::class, 'The post image could not be stored.');

        return $path;
    }

    private function deleteIfUnshared(string $path, int $exceptPostId): void
    {
        if (! Post::withTrashed()->whereKeyNot($exceptPostId)->where('featured_image', $path)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }

    /** @return array<string, mixed> */
    private function audit(Post $post): array
    {
        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'author_id' => $post->author_id,
            'category_id' => $post->post_category_id,
            'status' => $post->status->value,
            'visibility' => $post->visibility->value,
            'is_featured' => $post->is_featured,
            'featured_image' => $post->featured_image === null ? 'Not set' : 'Set',
            'published_at' => $post->published_at?->toIso8601String(),
            'scheduled_for' => $post->scheduled_for?->toIso8601String(),
            'tags' => $post->tags->modelKeys(),
        ];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function logMeaningfulChanges(User $actor, Post $post, array $old, array $new): void
    {
        foreach (['status', 'featured_image', 'author_id'] as $key) {
            if (($old[$key] ?? null) === $new[$key]) {
                continue;
            }
            $event = match ($key) {
                'status' => $new['status'],
                'featured_image' => 'image-changed',
                'author_id' => 'author-changed',
            };
            $this->activityLogger->log(
                logName: 'blog',
                event: "post.{$event}",
                description: str($event)->headline()->toString()." post \"{$post->title}\".",
                subject: $post,
                causer: $actor,
                oldValues: [$key => $old[$key] ?? null],
                newValues: [$key => $new[$key]],
            );
        }
    }
}
