<?php

use App\Actions\Books\ChangeBookStatus;
use App\Actions\Books\DeleteBook;
use App\Actions\Books\SaveBook;
use App\BookAvailabilityStatus;
use App\BookFormat;
use App\BookStatus;
use App\MediaType;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Media;
use App\Models\Person;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\BookSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function validBookData(array $overrides = []): array
{
    return array_merge([
        'title' => 'Walking in Faith',
        'slug' => 'walking-in-faith',
        'subtitle' => 'Trusting God in every season',
        'author_name' => 'TWM Leadership',
        'leadership_id' => null,
        'speaker_id' => null,
        'description' => 'A full description of this book.',
        'short_description' => 'A practical guide to living faithfully in every season.',
        'isbn' => '9781234567890',
        'publisher' => 'TWM Press',
        'publication_date' => '2026-07-31',
        'edition' => 'First edition',
        'language' => 'English',
        'page_count' => 180,
        'format' => BookFormat::Physical->value,
        'price' => 75,
        'currency' => 'GHS',
        'stock_quantity' => 25,
        'availability_status' => BookAvailabilityStatus::Available->value,
        'purchase_url' => 'https://example.test/purchase',
        'download_url' => null,
        'media_id' => null,
        'is_featured' => false,
        'is_free' => false,
        'status' => BookStatus::Draft->value,
        'published_at' => null,
    ], $overrides);
}

test('unauthorized users cannot access book administration', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('books.index'))
        ->assertForbidden();
});

test('users with book view permission can access administration', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::BooksView->value);

    $this->actingAs($actor)->get(route('books.index'))->assertOk();
});

test('role seeding grants additive book permissions without removing custom permissions', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole(RoleName::Editor);
    $customPermission = Permission::query()->create([
        'name' => 'custom.keep',
        'guard_name' => 'web',
    ]);
    $editor->roles->first()->givePermissionTo($customPermission);

    $this->seed(RolesAndPermissionsSeeder::class);
    $editor->refresh();

    expect($editor->can(PermissionName::BooksView))->toBeTrue()
        ->and($editor->can(PermissionName::BooksCreate))->toBeTrue()
        ->and($editor->can(PermissionName::BooksPublish))->toBeTrue()
        ->and($editor->can('custom.keep'))->toBeTrue();
});

test('authorized users can create books with leadership authors and activity history', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::BooksCreate->value);
    $leader = Person::factory()->create();

    $book = app(SaveBook::class)->handle($actor, validBookData([
        'author_name' => $leader->full_name,
        'leadership_id' => $leader->id,
    ]));

    expect($book->leadership->is($leader))->toBeTrue()
        ->and($book->speaker_id)->toBeNull()
        ->and($book->created_by)->toBe($actor->id)
        ->and(ActivityLog::query()->where('event', 'book.created')->exists())->toBeTrue();
});

test('book author cannot link leadership and speaker simultaneously', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::BooksCreate->value);
    [$leader, $speaker] = Person::factory()->count(2)->create();

    expect(fn () => app(SaveBook::class)->handle($actor, validBookData([
        'leadership_id' => $leader->id,
        'speaker_id' => $speaker->id,
    ])))->toThrow(ValidationException::class);
});

test('paid books require a valid price and free books clear their price', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::BooksCreate->value);

    expect(fn () => app(SaveBook::class)->handle($actor, validBookData(['price' => null])))
        ->toThrow(ValidationException::class);

    $free = app(SaveBook::class)->handle($actor, validBookData([
        'title' => 'Free Discipleship Guide',
        'slug' => 'free-discipleship-guide',
        'format' => BookFormat::Ebook->value,
        'is_free' => true,
        'price' => 100,
        'purchase_url' => null,
        'download_url' => 'https://example.test/download',
    ]));

    expect($free->price)->toBeNull()->and($free->displayPrice())->toBe('Free');
});

test('slugs are normalized and unique even across deleted books', function (): void {
    Book::factory()->create(['slug' => 'walking-in-faith'])->delete();
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::BooksCreate->value);

    $book = app(SaveBook::class)->handle($actor, validBookData(['slug' => 'Walking In Faith']));

    expect($book->slug)->toBe('walking-in-faith-2');
});

test('publishing requires a public image cover and publication details', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::BooksPublish->value);
    $book = Book::factory()->create(['media_id' => null]);

    expect(fn () => app(ChangeBookStatus::class)->publish($actor, $book))
        ->toThrow(ValidationException::class);

    $document = Media::factory()->create(['media_type' => MediaType::Document]);
    $book->update(['media_id' => $document->id]);

    expect(fn () => app(ChangeBookStatus::class)->publish($actor, $book->refresh()))
        ->toThrow(ValidationException::class);
});

test('publish unpublish archive and restore enforce explicit lifecycle rules', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::BooksPublish->value,
        PermissionName::BooksArchive->value,
        PermissionName::BooksRestore->value,
    ]);
    $cover = Media::factory()->create();
    $book = Book::factory()->create(['media_id' => $cover->id]);
    $action = app(ChangeBookStatus::class);

    $action->publish($actor, $book);
    expect($book->refresh()->status)->toBe(BookStatus::Published)
        ->and($book->published_at)->not->toBeNull();

    $action->unpublish($actor, $book);
    expect($book->refresh()->status)->toBe(BookStatus::Draft)
        ->and($book->published_at)->not->toBeNull();

    $action->archive($actor, $book);
    expect($book->refresh()->status)->toBe(BookStatus::Archived)
        ->and($book->is_featured)->toBeFalse();

    expect(fn () => $action->publish($actor, $book))
        ->toThrow(ValidationException::class);

    $action->restore($actor, $book);
    expect($book->refresh()->status)->toBe(BookStatus::Draft)
        ->and(ActivityLog::query()->where('event', 'book.restored')->exists())->toBeTrue();
});

test('only one currently published book can be featured', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::BooksPublish->value);
    $cover = Media::factory()->create();
    $first = Book::factory()->published()->featured()->create(['media_id' => $cover->id]);
    $second = Book::factory()->published()->create(['media_id' => $cover->id]);

    app(ChangeBookStatus::class)->toggleFeatured($actor, $second);

    expect($first->refresh()->is_featured)->toBeFalse()
        ->and($second->refresh()->is_featured)->toBeTrue();
});

test('draft and scheduled books are never publicly visible', function (): void {
    $cover = Media::factory()->create();
    $visible = Book::factory()->published()->create(['title' => 'Visible Book', 'media_id' => $cover->id]);
    $draft = Book::factory()->create(['title' => 'Draft Book', 'media_id' => $cover->id]);
    $scheduled = Book::factory()->scheduled()->create(['title' => 'Scheduled Book', 'media_id' => $cover->id]);
    $archived = Book::factory()->archived()->create(['title' => 'Archived Book', 'media_id' => $cover->id]);

    expect(Book::query()->published()->pluck('id')->all())->toBe([$visible->id]);
    $this->get(route('public.books.index'))->assertOk()
        ->assertSee($visible->title)
        ->assertDontSee($draft->title)
        ->assertDontSee($scheduled->title)
        ->assertDontSee($archived->title);
    $this->get(route('public.books.show', $draft))->assertNotFound();
});

test('public book search and filters expose only matching published books', function (): void {
    $cover = Media::factory()->create();
    $matching = Book::factory()->published()->free()->create([
        'title' => 'Kingdom Prayer',
        'author_name' => 'Jane Leader',
        'media_id' => $cover->id,
        'availability_status' => BookAvailabilityStatus::Available,
    ]);
    $other = Book::factory()->published()->create(['title' => 'Family Life', 'media_id' => $cover->id]);

    Livewire::test('pages::public.books.index')
        ->set('search', 'Kingdom')
        ->set('pricing', 'free')
        ->set('format', BookFormat::Ebook->value)
        ->assertSee($matching->title)
        ->assertDontSee($other->title);
});

test('soft deleting and restoring a book does not delete shared cover media', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([PermissionName::BooksDelete->value, PermissionName::BooksRestore->value]);
    $cover = Media::factory()->create();
    $book = Book::factory()->published()->featured()->create(['media_id' => $cover->id]);
    $action = app(DeleteBook::class);

    $action->delete($actor, $book);
    expect(Book::query()->find($book->id))->toBeNull()->and($cover->fresh())->not->toBeNull();

    $action->restore($actor, $book);
    expect($book->refresh()->status)->toBe(BookStatus::Draft)
        ->and($book->is_featured)->toBeFalse()
        ->and($cover->fresh())->not->toBeNull();
});

test('livewire create form stores a media library cover and linked author', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::BooksCreate->value,
        PermissionName::MediaView->value,
    ]);
    $cover = Media::factory()->create();

    Livewire::actingAs($actor)->test('pages::books.create')
        ->set('form.title', 'A New Book')
        ->set('form.slug', 'a-new-book')
        ->set('form.authorName', 'Guest Writer')
        ->set('form.shortDescription', 'A useful book for every reader.')
        ->set('form.format', BookFormat::Physical->value)
        ->set('form.price', '25.00')
        ->set('form.currency', 'GHS')
        ->set('form.availabilityStatus', BookAvailabilityStatus::Available->value)
        ->set('form.mediaIds', [$cover->id])
        ->call('saveDraft')
        ->assertHasNoErrors()
        ->assertRedirect();

    $book = Book::query()->where('slug', 'a-new-book')->firstOrFail();
    expect($book->media_id)->toBe($cover->id)
        ->and($book->status)->toBe(BookStatus::Draft);
});

test('livewire workflow actions authorize on the server', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::BooksView->value);
    $book = Book::factory()->create();

    Livewire::actingAs($actor)->test('pages::books.index')
        ->call('confirm', $book->id, 'publish')
        ->assertForbidden();
});

test('bulk actions authorize and publish each selected complete book', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::BooksView->value,
        PermissionName::BooksPublish->value,
    ]);
    $cover = Media::factory()->create();
    $books = Book::factory()->count(2)->create(['media_id' => $cover->id]);

    Livewire::actingAs($actor)->test('pages::books.index')
        ->set('selected', $books->modelKeys())
        ->call('confirmBulk', 'publish')
        ->call('executeConfirmed')
        ->assertHasNoErrors();

    expect(Book::query()->published()->count())->toBe(2)
        ->and(ActivityLog::query()->where('event', 'book.published')->count())->toBe(2);
});

test('admin detail page exposes and executes authorized workflow actions', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::BooksView->value,
        PermissionName::BooksPublish->value,
    ]);
    $cover = Media::factory()->create();
    $book = Book::factory()->create(['media_id' => $cover->id]);

    Livewire::actingAs($actor)->test('pages::books.show', ['book' => $book])
        ->call('confirm', 'publish')
        ->call('executeConfirmed')
        ->assertHasNoErrors()
        ->assertSee('Unpublish');

    expect($book->refresh()->status)->toBe(BookStatus::Published);
});

test('book seeding is idempotent and does not overwrite existing records', function (): void {
    Book::factory()->create([
        'slug' => 'walking-in-faith',
        'title' => 'Production Title',
    ]);

    $this->seed(BookSeeder::class);
    $this->seed(BookSeeder::class);

    expect(Book::query()->where('slug', 'walking-in-faith')->count())->toBe(1)
        ->and(Book::query()->where('slug', 'walking-in-faith')->value('title'))->toBe('Production Title')
        ->and(Book::query()->count())->toBe(6);
});

test('create permission alone cannot publish or feature a book', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::BooksCreate->value);
    $cover = Media::factory()->create();

    expect(fn () => app(SaveBook::class)->handle($actor, validBookData([
        'media_id' => $cover->id,
        'status' => BookStatus::Published->value,
        'published_at' => now(),
    ])))->toThrow(AuthorizationException::class);
});
