<?php

use App\Actions\Blog\ChangePostStatus;
use App\Actions\Blog\DeletePost;
use App\Actions\Blog\DuplicatePost;
use App\Actions\Blog\SavePost;
use App\Blog\HtmlSanitizer;
use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\User;
use App\PermissionName;
use App\PostStatus;
use App\PostVisibility;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function validPostData(User $author, array $overrides = []): array
{
    return array_merge([
        'author_id' => $author->id,
        'post_category_id' => null,
        'title' => 'Walking Faithfully Together',
        'slug' => 'walking-faithfully-together',
        'excerpt' => 'An encouraging word for our church family.',
        'content' => '<p>Let us encourage one another.</p>',
        'featured_image_alt_text' => null,
        'status' => PostStatus::Draft->value,
        'visibility' => PostVisibility::Public->value,
        'is_featured' => false,
        'allow_comments' => false,
        'published_at' => null,
        'scheduled_for' => null,
        'archived_at' => null,
        'meta_title' => null,
        'meta_description' => null,
        'canonical_url' => null,
    ], $overrides);
}

test('post administration requires permission', function (): void {
    $this->actingAs(User::factory()->create())->get(route('posts.index'))->assertForbidden();

    $editor = User::factory()->create();
    $editor->givePermissionTo(PermissionName::PostsView->value);
    $this->actingAs($editor)->get(route('posts.index'))->assertOk();
});

test('authorized users create sanitized posts with taxonomy and audit records', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PostsCreate->value);
    $category = PostCategory::factory()->create();
    $tags = Tag::factory()->count(2)->create();

    $post = app(SavePost::class)->handle($actor, validPostData($actor, [
        'post_category_id' => $category->id,
        'content' => '<p>Welcome</p><script>alert(1)</script>',
    ]), $tags->modelKeys());

    expect($post->category->is($category))->toBeTrue()
        ->and($post->tags)->toHaveCount(2)
        ->and($post->content)->not->toContain('<script')
        ->and(ActivityLog::query()->where('event', 'post.created')->exists())->toBeTrue();
});

test('post taxonomy and author relationships are available in both directions', function (): void {
    $author = User::factory()->create();
    $category = PostCategory::factory()->create();
    $tag = Tag::factory()->create();
    $post = Post::factory()->for($author, 'author')->for($category, 'category')->create();
    $post->tags()->attach($tag);

    expect($post->author->is($author))->toBeTrue()
        ->and($post->category->is($category))->toBeTrue()
        ->and($post->tags->first()->is($tag))->toBeTrue()
        ->and($author->posts->first()->is($post))->toBeTrue()
        ->and($category->posts->first()->is($post))->toBeTrue()
        ->and($tag->posts->first()->is($post))->toBeTrue();
});

test('manual slugs are normalized and made unique', function (): void {
    Post::factory()->create(['slug' => 'church-news']);
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PostsCreate->value);

    $post = app(SavePost::class)->handle($actor, validPostData($actor, ['slug' => 'Church News']));

    expect($post->slug)->toBe('church-news-2');
});

test('create permission alone cannot publish or assign another author', function (): void {
    $actor = User::factory()->create();
    $other = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PostsCreate->value);

    expect(fn () => app(SavePost::class)->handle($actor, validPostData($other)))->toThrow(AuthorizationException::class);
    expect(fn () => app(SavePost::class)->handle($actor, validPostData($actor, [
        'status' => PostStatus::Published->value,
        'published_at' => now(),
    ])))->toThrow(AuthorizationException::class);
});

test('featured images can be replaced and safely removed', function (): void {
    Storage::fake('public');
    $actor = User::factory()->create();
    $actor->givePermissionTo([PermissionName::PostsCreate->value, PermissionName::PostsUpdate->value]);
    $post = app(SavePost::class)->handle($actor, validPostData($actor), featuredImage: UploadedFile::fake()->image('first.jpg'));
    $oldImage = $post->featured_image;

    $post = app(SavePost::class)->handle($actor, validPostData($actor), featuredImage: UploadedFile::fake()->image('second.webp'), post: $post);

    Storage::disk('public')->assertMissing($oldImage);
    Storage::disk('public')->assertExists($post->featured_image);
});

test('post form validates required publication fields scheduling and uploads', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([PermissionName::PostsCreate->value, PermissionName::PostsPublish->value]);

    Livewire::actingAs($actor)->test('pages::posts.create')
        ->set('form.title', '')
        ->set('form.slug', '')
        ->set('form.status', PostStatus::Scheduled->value)
        ->set('form.scheduledFor', now()->subHour()->format('Y-m-d\TH:i'))
        ->set('form.featuredImage', UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['form.title', 'form.slug', 'form.content', 'form.scheduledFor', 'form.featuredImage']);
});

test('only due public posts appear on the public blog', function (): void {
    $visible = Post::factory()->published()->create(['title' => 'Visible Article']);
    $draft = Post::factory()->create(['title' => 'Draft Article']);
    $future = Post::factory()->scheduled()->create(['title' => 'Future Article']);
    $overdue = Post::factory()->due()->create(['title' => 'Overdue Article']);
    $private = Post::factory()->published()->private()->create(['title' => 'Private Article']);

    $this->get(route('blog.index'))->assertOk()->assertSee($visible->title)
        ->assertDontSee($draft->title)->assertDontSee($future->title)->assertSee($overdue->title)->assertDontSee($private->title);
    $this->get(route('blog.show', $draft))->assertNotFound();
});

test('scheduled publishing command is idempotent', function (): void {
    $due = Post::factory()->due()->create();
    $future = Post::factory()->scheduled()->create();

    $this->artisan('blog:publish-scheduled')->assertSuccessful();
    $firstPublishedAt = $due->refresh()->published_at;
    $this->artisan('blog:publish-scheduled')->assertSuccessful();

    expect($due->refresh()->status)->toBe(PostStatus::Published)
        ->and($due->published_at->equalTo($firstPublishedAt))->toBeTrue()
        ->and($future->refresh()->status)->toBe(PostStatus::Scheduled)
        ->and(ActivityLog::query()->where('event', 'post.published')->count())->toBe(1);
});

test('posts can be published archived duplicated deleted and restored', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::PostsPublish->value, PermissionName::PostsArchive->value,
        PermissionName::PostsCreate->value, PermissionName::PostsDelete->value,
        PermissionName::PostsRestore->value,
    ]);
    $post = Post::factory()->create();
    app(ChangePostStatus::class)->publish($actor, $post);
    $copy = app(DuplicatePost::class)->handle($actor, $post);
    app(ChangePostStatus::class)->archive($actor, $post);
    app(DeletePost::class)->delete($actor, $post);
    app(DeletePost::class)->restore($actor, $post);

    expect($post->refresh()->status)->toBe(PostStatus::Draft)
        ->and($copy->status)->toBe(PostStatus::Draft)
        ->and($copy->slug)->not->toBe($post->slug)
        ->and(ActivityLog::query()->where('event', 'post.duplicated')->exists())->toBeTrue();
});

test('only highly privileged users can permanently delete a post', function (): void {
    $post = Post::factory()->create();
    $post->delete();
    $editor = User::factory()->create();
    $editor->givePermissionTo(PermissionName::PostsDelete->value);

    expect(fn () => app(DeletePost::class)->forceDelete($editor, $post))->toThrow(AuthorizationException::class);

    $administrator = User::factory()->create();
    $administrator->givePermissionTo(PermissionName::PostsForceDelete->value);
    app(DeletePost::class)->forceDelete($administrator, $post);
    expect(Post::withTrashed()->find($post->id))->toBeNull();
});

test('admin filters and public search return only matching permitted posts', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PostsView->value);
    $category = PostCategory::factory()->create();
    $matching = Post::factory()->published()->featured()->for($category, 'category')->create(['title' => 'Kingdom Outreach Report']);
    $draft = Post::factory()->for($category, 'category')->create(['title' => 'Kingdom Draft Notes']);
    $other = Post::factory()->published()->create(['title' => 'Weekly Devotional']);

    Livewire::actingAs($actor)->test('pages::posts.index')
        ->set('search', 'Kingdom')
        ->set('status', PostStatus::Published->value)
        ->set('category', (string) $category->id)
        ->set('featured', '1')
        ->assertSee($matching->title)
        ->assertDontSee($draft->title)
        ->assertDontSee($other->title);

    $this->get(route('blog.index', ['search' => 'Kingdom']))
        ->assertSee($matching->title)
        ->assertDontSee($draft->title);
});

test('category deletion is blocked while tag deletion safely detaches posts', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([PermissionName::PostCategoriesManage->value, PermissionName::PostTagsManage->value]);
    $category = PostCategory::factory()->create();
    $tag = Tag::factory()->create();
    $post = Post::factory()->for($category, 'category')->create();
    $post->tags()->attach($tag);

    Livewire::actingAs($actor)->test('pages::post-categories.index')
        ->call('confirmDelete', $category->id)
        ->call('delete')
        ->assertHasErrors(['delete']);
    expect($category->fresh())->not->toBeNull();

    Livewire::actingAs($actor)->test('pages::post-tags.index')
        ->call('confirmDelete', $tag->id)
        ->call('delete');
    expect($tag->fresh())->toBeNull()
        ->and($post->fresh()->tags)->toBeEmpty();
});

test('signed previews require authentication permission and a valid signature', function (): void {
    $post = Post::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionName::PostsPreview->value);
    $url = URL::temporarySignedRoute('blog.preview', now()->addMinute(), ['post' => $post]);

    $this->actingAs($viewer)->get($url)->assertOk()->assertSee($post->title);
    $this->actingAs($viewer)->get(route('blog.preview', $post))->assertForbidden();
});

test('html sanitizer removes dangerous elements attributes and urls', function (): void {
    $clean = app(HtmlSanitizer::class)->sanitize('<p onclick="evil()">Safe <a href="javascript:evil()">link</a></p><iframe src="x"></iframe>');

    expect($clean)->toContain('<p>Safe')
        ->not->toContain('onclick')
        ->not->toContain('javascript:')
        ->not->toContain('iframe');
});
