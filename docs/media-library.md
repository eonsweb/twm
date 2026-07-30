# Media & Assets Library

The admin library is available at `/admin/media` to users with `media.view`. Files are stored through Laravel's filesystem abstraction; logical folders live only in the database so moving an asset does not change its URL.

## Storage

- `MEDIA_DISK` defaults to `public` for public assets.
- `MEDIA_PRIVATE_DISK` defaults to `local` for private assets.
- Run `php artisan storage:link` when using the local public disk.
- Physical paths follow `media/{type}/{year}/{month}/{uuid}.{extension}`.
- Private files have no permanent public URL and are served only by authorized preview/download routes.

Supported extensions, MIME allowlists, and per-type size limits are centralized in `config/media.php`. SVG, HTML, JavaScript, and executable formats are intentionally disabled. PHP `upload_max_filesize` and `post_max_size`, the web server request limit, and Livewire's temporary upload rules must be at least as large as the intended upload.

## Permissions

The module uses `media.view`, `media.create`, `media.upload` (legacy compatibility), `media.update`, `media.delete`, `media.restore`, `media.force-delete`, `media.download`, `media.manage-folders`, and `media.manage-private`. The roles seeder adds permissions without deleting unrelated permissions or records.

## Reuse

`mediables` is the polymorphic usage table. It stores a media ID, morph target, collection name, position, and collection-specific JSON metadata. Existing path-based fields in older modules remain compatible and can be migrated gradually.

Models can opt in with the `App\Concerns\HasMedia` trait and query a collection with `$model->mediaCollection('gallery')`.

The reusable Livewire picker supports:

```blade
<livewire:media-picker
    wire:model="featuredMediaIds"
    :allowed-types="['image']"
    :multiple="false"
    collection="featured_image"
/>
```

It emits `media-selected` with the selected IDs and collection name.

## Maintenance

`php artisan media:audit` reports missing database files, orphaned physical files, and trash records beyond the configured retention period. `php artisan media:audit --cleanup` can remove only reported orphaned physical files and requires explicit confirmation. It never automatically deletes database records or files still associated with media records.
