<?php

use App\Actions\Media\ManageMedia;
use App\Actions\Media\ManageMediaFolder;
use App\Media\MediaFileService;
use App\MediaStatus;
use App\MediaType;
use App\MediaVisibility;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function mediaUser(PermissionName ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission->value);
        $user->givePermissionTo($permission->value);
    }

    return $user;
}

function storeTestMedia(User $actor, ?UploadedFile $file = null, ?MediaFolder $folder = null): Media
{
    return app(MediaFileService::class)->store(
        $file ?? UploadedFile::fake()->image('worship.jpg', 1200, 800),
        $actor,
        $folder,
    );
}

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

test('a user with view permission can access the media library', function (): void {
    $user = mediaUser(PermissionName::MediaView);

    $this->actingAs($user)->get(route('media.index'))->assertOk()->assertSee('Media Library');
});

test('media permissions are seeded additively for the expected roles', function (): void {
    $custom = Permission::findOrCreate('custom.existing-permission');
    $administrator = Role::findOrCreate(RoleName::Administrator->value);
    $administrator->givePermissionTo($custom);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect($administrator->refresh()->hasPermissionTo($custom))->toBeTrue()
        ->and($administrator->hasPermissionTo(PermissionName::MediaForceDelete))->toBeTrue()
        ->and(Role::findByName(RoleName::Editor)->hasPermissionTo(PermissionName::MediaCreate))->toBeTrue()
        ->and(Role::findByName(RoleName::MediaManager)->hasPermissionTo(PermissionName::MediaManagePrivate))->toBeTrue()
        ->and(Role::findByName(RoleName::Editor)->hasPermissionTo(PermissionName::MediaForceDelete))->toBeFalse();
});

test('a user without view permission cannot access the media library', function (): void {
    $this->actingAs(User::factory()->create())->get(route('media.index'))->assertForbidden();
});

test('authorized users can upload an image and document', function (): void {
    $user = mediaUser(PermissionName::MediaView, PermissionName::MediaCreate);

    $image = storeTestMedia($user);
    $document = storeTestMedia($user, UploadedFile::fake()->create('service-guide.pdf', 120, 'application/pdf'));

    expect($image->media_type)->toBe(MediaType::Image)
        ->and($image->width)->toBe(1200)
        ->and($image->height)->toBe(800)
        ->and($document->media_type)->toBe(MediaType::Document)
        ->and($document->mime_type)->toBe('application/pdf');
    Storage::disk('public')->assertExists($image->path);
    Storage::disk('public')->assertExists($document->path);
});

test('multiple files can be uploaded through the library component', function (): void {
    $user = mediaUser(PermissionName::MediaView, PermissionName::MediaCreate);

    Livewire::actingAs($user)
        ->test('pages::media.index')
        ->set('showUploadModal', true)
        ->set('files', [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.png'),
        ])
        ->assertSet('files.0', fn (mixed $file): bool => $file instanceof TemporaryUploadedFile)
        ->assertSee('temporaryUploading || false', escape: false)
        ->call('saveMedia')
        ->assertHasNoErrors()
        ->assertSet('files', [])
        ->assertSet('showUploadModal', false)
        ->assertDispatched('toast-show');

    $storedMedia = Media::query()->orderBy('id')->get();

    expect($storedMedia)->toHaveCount(2);

    foreach ($storedMedia as $media) {
        expect($media->disk)->toBe('public')
            ->and($media->path)->toMatch('#^media/images/\d{4}/\d{2}/[0-9a-f-]+\.(jpg|png)$#')
            ->and($media->path)->not->toContain(storage_path());
        Storage::disk('public')->assertExists($media->path);
    }
});

test('the upload interface exposes distinct temporary and permanent loading states', function (): void {
    $user = mediaUser(PermissionName::MediaView, PermissionName::MediaCreate);

    $this->actingAs($user)
        ->get(route('media.index'))
        ->assertOk()
        ->assertSee('x-on:livewire-upload-start', escape: false)
        ->assertSee('x-on:livewire-upload-finish', escape: false)
        ->assertSee('x-on:livewire-upload-error', escape: false)
        ->assertSee('x-on:livewire-upload-cancel', escape: false)
        ->assertSee('wire:submit="saveMedia"', escape: false)
        ->assertSee('wire:target="saveMedia"', escape: false)
        ->assertDontSee('wire:click="saveMedia"', escape: false)
        ->assertSee('$wire.$cancelUpload', escape: false);
});

test('temporary upload validation supports the largest configured media type', function (): void {
    $rules = config('livewire.temporary_file_upload.rules');

    expect($rules)->toBeArray()
        ->and($rules)->toContain('max:'.app(MediaFileService::class)->maximumKilobytes());
});

test('closing the upload modal clears temporary files and validation errors', function (): void {
    $user = mediaUser(PermissionName::MediaView, PermissionName::MediaCreate);

    Livewire::actingAs($user)
        ->test('pages::media.index')
        ->set('showUploadModal', true)
        ->call('saveMedia')
        ->assertHasErrors(['files'])
        ->call('closeUploadModal')
        ->assertHasNoErrors()
        ->assertSet('files', [])
        ->assertSet('showUploadModal', false);
});

test('a missing permanent storage disk produces a recoverable per-file error', function (): void {
    $user = mediaUser(PermissionName::MediaView, PermissionName::MediaCreate);
    config()->set('media.disk', 'missing-media-disk');

    Livewire::actingAs($user)
        ->test('pages::media.index')
        ->set('showUploadModal', true)
        ->set('files', [UploadedFile::fake()->image('failure.png')])
        ->call('saveMedia')
        ->assertHasErrors(['files.0'])
        ->assertSet('showUploadModal', true)
        ->assertDispatched('toast-show');

    expect(Media::query()->count())->toBe(0);
});

test('a failed new upload preserves previously stored media', function (): void {
    $user = mediaUser(PermissionName::MediaView, PermissionName::MediaCreate);
    $existing = storeTestMedia($user);
    config()->set('media.disk', 'missing-media-disk');

    Livewire::actingAs($user)
        ->test('pages::media.index')
        ->set('files', [UploadedFile::fake()->image('new-failure.png')])
        ->call('saveMedia')
        ->assertHasErrors(['files.0']);

    expect(Media::query()->pluck('id')->all())->toBe([$existing->id]);
    Storage::disk('public')->assertExists($existing->path);
});

test('a database failure cleans up the newly stored physical file', function (): void {
    $user = mediaUser(PermissionName::MediaCreate);
    $originalConnection = config('database.default');
    config()->set('database.default', 'missing-media-database');

    try {
        expect(fn () => storeTestMedia($user))->toThrow(InvalidArgumentException::class);
    } finally {
        config()->set('database.default', $originalConnection);
    }

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('users without create permission cannot upload through livewire', function (): void {
    $user = mediaUser(PermissionName::MediaView);

    Livewire::actingAs($user)
        ->test('pages::media.index')
        ->set('files', [UploadedFile::fake()->image('blocked.jpg')])
        ->call('saveMedia')
        ->assertForbidden();

    expect(Media::query()->count())->toBe(0);
});

test('dangerous extensions and mismatched mime content are rejected', function (): void {
    $user = mediaUser(PermissionName::MediaCreate);

    expect(fn () => storeTestMedia($user, UploadedFile::fake()->create('shell.php', 1, 'application/x-php')))
        ->toThrow(ValidationException::class);

    expect(fn () => storeTestMedia($user, UploadedFile::fake()->create('fake.jpg', 1, 'text/plain')))
        ->toThrow(ValidationException::class);

    expect(Media::query()->count())->toBe(0);
});

test('per-type size limits are enforced', function (): void {
    $user = mediaUser(PermissionName::MediaCreate);

    expect(fn () => storeTestMedia(
        $user,
        UploadedFile::fake()->create('too-large.pdf', 25 * 1024 + 1, 'application/pdf'),
    ))->toThrow(ValidationException::class);
});

test('oversized images are rejected without creating a media record', function (): void {
    $user = mediaUser(PermissionName::MediaCreate);

    expect(fn () => storeTestMedia(
        $user,
        UploadedFile::fake()->create('too-large.png', 10 * 1024 + 1, 'image/png'),
    ))->toThrow(ValidationException::class);

    expect(Media::query()->count())->toBe(0);
});

test('private uploads use the private disk and have no public url', function (): void {
    $user = mediaUser(PermissionName::MediaCreate);

    $media = app(MediaFileService::class)->store(
        UploadedFile::fake()->image('private.jpg'),
        $user,
        visibility: MediaVisibility::Private,
    );

    expect($media->disk)->toBe('local')->and($media->publicUrl())->toBeNull();
    Storage::disk('local')->assertExists($media->path);
});

test('metadata can be updated with validated values', function (): void {
    $user = mediaUser(PermissionName::MediaUpdate, PermissionName::MediaManagePrivate);
    $media = storeTestMedia($user);
    $folder = MediaFolder::factory()->create();

    app(ManageMedia::class)->update($user, $media, [
        'name' => 'Sunday Worship',
        'alt_text' => 'The worship team leading the congregation',
        'caption' => 'Sunday gathering',
        'description' => 'A reusable website image.',
        'credit' => 'Communications Team',
        'copyright' => 'TWM',
        'source_url' => 'https://example.com/source',
        'media_folder_id' => $folder->id,
        'visibility' => MediaVisibility::Private->value,
        'status' => MediaStatus::Archived->value,
        'is_featured' => true,
    ]);

    expect($media->refresh())
        ->name->toBe('Sunday Worship')
        ->alt_text->toBe('The worship team leading the congregation')
        ->folder->is($folder)->toBeTrue()
        ->visibility->toBe(MediaVisibility::Private)
        ->status->toBe(MediaStatus::Archived)
        ->is_featured->toBeTrue();
    Storage::disk('public')->assertMissing($media->path);
    Storage::disk('local')->assertExists($media->path);
});

test('invalid metadata values are rejected', function (array $changes): void {
    $user = mediaUser(PermissionName::MediaUpdate);
    $media = Media::factory()->create(['uploaded_by' => $user]);
    $data = [
        'name' => 'Valid name',
        'media_folder_id' => null,
        'visibility' => 'public',
        'status' => 'active',
        'is_featured' => false,
        ...$changes,
    ];

    expect(fn () => app(ManageMedia::class)->update($user, $media, $data))
        ->toThrow(ValidationException::class);
})->with([
    'visibility' => [['visibility' => 'secret']],
    'folder' => [['media_folder_id' => 999999]],
    'source URL' => [['source_url' => 'javascript:alert(1)']],
]);

test('folders can be nested and renamed', function (): void {
    $user = mediaUser(PermissionName::MediaManageFolders);
    $manager = app(ManageMediaFolder::class);
    $parent = $manager->save($user, ['name' => 'Sermons', 'parent_id' => null]);
    $child = $manager->save($user, ['name' => 'Thumbnails', 'parent_id' => $parent->id]);
    $manager->save($user, ['name' => 'Covers', 'parent_id' => $parent->id], $child);

    expect($child->refresh()->name)->toBe('Covers')
        ->and($child->parent->is($parent))->toBeTrue();
});

test('folder names are unique within the same parent', function (): void {
    $user = mediaUser(PermissionName::MediaManageFolders);
    $manager = app(ManageMediaFolder::class);
    $manager->save($user, ['name' => 'Events', 'parent_id' => null]);

    expect(fn () => $manager->save($user, ['name' => 'Events', 'parent_id' => null]))
        ->toThrow(ValidationException::class);
});

test('folders cannot become their own parent or create a cycle', function (): void {
    $user = mediaUser(PermissionName::MediaManageFolders);
    $manager = app(ManageMediaFolder::class);
    $parent = $manager->save($user, ['name' => 'Parent', 'parent_id' => null]);
    $child = $manager->save($user, ['name' => 'Child', 'parent_id' => $parent->id]);

    expect(fn () => $manager->save($user, ['name' => 'Parent', 'parent_id' => $parent->id], $parent))
        ->toThrow(ValidationException::class);
    expect(fn () => $manager->save($user, ['name' => 'Parent', 'parent_id' => $child->id], $parent))
        ->toThrow(ValidationException::class);
});

test('a non-empty folder cannot be deleted', function (): void {
    $user = mediaUser(PermissionName::MediaManageFolders);
    $folder = MediaFolder::factory()->create();
    Media::factory()->create(['media_folder_id' => $folder]);

    expect(fn () => app(ManageMediaFolder::class)->delete($user, $folder))
        ->toThrow(ValidationException::class);

    $this->assertModelExists($folder);
});

test('file replacement keeps the record and removes the old file after success', function (): void {
    $user = mediaUser(PermissionName::MediaUpdate);
    $media = storeTestMedia($user);
    $oldPath = $media->path;

    app(MediaFileService::class)->replace(
        $media,
        UploadedFile::fake()->create('replacement.pdf', 40, 'application/pdf'),
        $user,
    );

    expect($media->refresh()->id)->not->toBeNull()
        ->and($media->media_type)->toBe(MediaType::Document)
        ->and($media->path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($media->path);
});

test('a failed replacement leaves the old file untouched', function (): void {
    $user = mediaUser(PermissionName::MediaUpdate);
    $media = storeTestMedia($user);
    $oldPath = $media->path;

    expect(fn () => app(MediaFileService::class)->replace(
        $media,
        UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
        $user,
    ))->toThrow(ValidationException::class);

    expect($media->refresh()->path)->toBe($oldPath);
    Storage::disk('public')->assertExists($oldPath);
});

test('soft deletion retains the physical file and permanent deletion removes it', function (): void {
    $user = mediaUser(PermissionName::MediaDelete, PermissionName::MediaForceDelete);
    $media = storeTestMedia($user);
    $path = $media->path;
    $manager = app(ManageMedia::class);

    $manager->delete($user, $media);
    Storage::disk('public')->assertExists($path);
    expect(Media::onlyTrashed()->find($media->id))->not->toBeNull();

    $manager->forceDelete($user, $media);
    Storage::disk('public')->assertMissing($path);
    expect(Media::withTrashed()->find($media->id))->toBeNull();
});

test('private downloads require private media permission', function (): void {
    $owner = mediaUser(PermissionName::MediaCreate);
    $media = app(MediaFileService::class)->store(
        UploadedFile::fake()->image('private.jpg'),
        $owner,
        visibility: MediaVisibility::Private,
    );
    $blocked = mediaUser(PermissionName::MediaDownload);

    $this->actingAs($blocked)->get(route('media.download', $media))->assertForbidden();

    $allowed = mediaUser(PermissionName::MediaDownload, PermissionName::MediaManagePrivate);
    $this->actingAs($allowed)->get(route('media.download', $media))->assertDownload('private.jpg');
    expect($media->refresh()->download_count)->toBe(1);
});

test('search filters sorting and pagination are applied by the library', function (): void {
    $user = mediaUser(PermissionName::MediaView);
    Media::factory()->create(['name' => 'Alpha Worship', 'original_name' => 'alpha.jpg', 'media_type' => MediaType::Image, 'visibility' => MediaVisibility::Public]);
    Media::factory()->create(['name' => 'Budget Sheet', 'original_name' => 'budget.xlsx', 'media_type' => MediaType::Spreadsheet, 'extension' => 'xlsx', 'visibility' => MediaVisibility::Public]);

    Livewire::actingAs($user)->test('pages::media.index')
        ->set('search', 'alpha')
        ->assertSee('Alpha Worship')
        ->assertDontSee('Budget Sheet')
        ->set('search', '')
        ->set('type', MediaType::Spreadsheet->value)
        ->assertSee('Budget Sheet')
        ->assertDontSee('Alpha Worship')
        ->set('sort', 'largest')
        ->set('perPage', 24)
        ->assertHasNoErrors();
});

test('authorized bulk actions archive and move selected assets', function (): void {
    $user = mediaUser(PermissionName::MediaView, PermissionName::MediaUpdate);
    $folder = MediaFolder::factory()->create();
    $assets = Media::factory()->count(2)->create(['visibility' => MediaVisibility::Public]);

    Livewire::actingAs($user)->test('pages::media.index')
        ->set('selected', $assets->modelKeys())
        ->set('pendingAction', 'move')
        ->set('bulkFolder', (string) $folder->id)
        ->call('executeBulk')
        ->set('selected', $assets->modelKeys())
        ->set('pendingAction', 'archive')
        ->call('executeBulk')
        ->assertHasNoErrors();

    expect(Media::query()->where('media_folder_id', $folder->id)->count())->toBe(2)
        ->and(Media::query()->archived()->count())->toBe(2);
});

test('the audit command reports missing files without deleting records', function (): void {
    $media = Media::factory()->create();

    $this->artisan('media:audit')
        ->expectsOutputToContain('Database records with missing files')
        ->assertSuccessful();

    $this->assertModelExists($media);
});
