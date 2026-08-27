<?php

use App\MediaType;
use App\MediaVisibility;
use App\Models\Media;
use App\Models\User;
use App\PermissionName;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function pickerUser(PermissionName ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission->value);
        $user->givePermissionTo($permission->value);
    }

    return $user;
}

test('the media picker supports single selection', function (): void {
    $user = pickerUser(PermissionName::MediaView);
    $first = Media::factory()->create(['visibility' => MediaVisibility::Public]);
    $second = Media::factory()->create(['visibility' => MediaVisibility::Public]);

    Livewire::actingAs($user)
        ->test('media-picker')
        ->call('show')
        ->call('toggle', $first->id)
        ->call('toggle', $second->id)
        ->call('confirm')
        ->assertSet('mediaIds', [$second->id]);
});

test('the media picker supports multiple selection and maximum counts', function (): void {
    $user = pickerUser(PermissionName::MediaView);
    $assets = Media::factory()->count(3)->create(['visibility' => MediaVisibility::Public]);

    Livewire::actingAs($user)
        ->test('media-picker', ['multiple' => true, 'maximum' => 2])
        ->call('show')
        ->call('toggle', $assets[0]->id)
        ->call('toggle', $assets[1]->id)
        ->call('toggle', $assets[2]->id)
        ->call('confirm')
        ->assertSet('mediaIds', [$assets[1]->id, $assets[2]->id]);
});

test('the media picker enforces allowed types', function (): void {
    $user = pickerUser(PermissionName::MediaView);
    $document = Media::factory()->create([
        'media_type' => MediaType::Document,
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'visibility' => MediaVisibility::Public,
    ]);

    Livewire::actingAs($user)
        ->test('media-picker', ['allowedTypes' => [MediaType::Image->value]])
        ->call('show')
        ->call('toggle', $document->id)
        ->assertStatus(422);
});

test('the media picker enforces allowed extensions', function (): void {
    $user = pickerUser(PermissionName::MediaView);
    $jpeg = Media::factory()->create(['extension' => 'jpg', 'visibility' => MediaVisibility::Public]);

    Livewire::actingAs($user)
        ->test('media-picker', [
            'allowedTypes' => [MediaType::Image->value],
            'allowedExtensions' => ['ico', 'png', 'svg', 'webp'],
        ])
        ->call('show')
        ->call('toggle', $jpeg->id)
        ->assertStatus(422);
});

test('existing media picker selections are displayed and can be removed', function (): void {
    $user = pickerUser(PermissionName::MediaView);
    $media = Media::factory()->create(['visibility' => MediaVisibility::Public]);

    Livewire::actingAs($user)
        ->test('media-picker', ['mediaIds' => [$media->id]])
        ->assertSee($media->name)
        ->call('remove', $media->id)
        ->assertSet('mediaIds', []);
});

test('the media picker upload action works without rendering a nested form', function (): void {
    Storage::fake('public');
    $user = pickerUser(PermissionName::MediaView, PermissionName::MediaCreate);

    Livewire::actingAs($user)
        ->test('media-picker', ['allowedTypes' => [MediaType::Image->value]])
        ->assertSee('wire:click="uploadNew"', false)
        ->assertDontSee('wire:submit="uploadNew"', false)
        ->set('upload', UploadedFile::fake()->image('event-artwork.jpg'))
        ->call('uploadNew')
        ->assertCount('pending', 1)
        ->call('confirm')
        ->assertCount('mediaIds', 1);

    $media = Media::query()->sole();
    Storage::disk($media->disk)->assertExists($media->path);
});
