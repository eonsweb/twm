<?php

use App\Actions\Sermons\ChangeSermonStatus;
use App\Actions\Sermons\DuplicateSermon;
use App\Actions\Sermons\SaveSermon;
use App\Models\ActivityLog;
use App\Models\Person;
use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\Topic;
use App\Models\User;
use App\PermissionName;
use App\SermonMediaPlatform;
use App\SermonMediaType;
use App\Sermons\ExternalMedia;
use App\SermonStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function validSermonData(Person $speaker, array $overrides = []): array
{
    return array_merge([
        'title' => 'Walking by Faith',
        'slug' => 'walking-by-faith',
        'summary' => 'A message about living by faith.',
        'description' => 'Safe plain-text sermon notes.',
        'scripture_reference' => 'Romans 8:28',
        'sermon_date' => now()->toDateString(),
        'duration_seconds' => 2700,
        'external_media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'media_platform' => SermonMediaPlatform::YouTube->value,
        'media_type' => SermonMediaType::Video->value,
        'embed_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'external_thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        'speaker_id' => $speaker->id,
        'sermon_series_id' => null,
        'service_name' => 'Sunday Worship',
        'location' => 'Main Campus',
        'status' => SermonStatus::Draft->value,
        'published_at' => null,
        'scheduled_at' => null,
        'is_featured' => false,
        'display_order' => 0,
        'seo_title' => 'Walking by Faith Sermon',
        'seo_description' => 'Watch Walking by Faith.',
    ], $overrides);
}

test('supported video URLs are normalized to safe embeds', function (string $url, SermonMediaPlatform $platform, string $embed): void {
    $media = app(ExternalMedia::class)->inspect($url);

    expect($media['original_url'])->toBe($url)
        ->and($media['platform'])->toBe($platform)
        ->and($media['embed_url'])->toBe($embed)
        ->and(app(ExternalMedia::class)->isEmbeddableUrl($media['embed_url']))->toBeTrue();
})->with([
    'youtube watch' => [
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        SermonMediaPlatform::YouTube,
        'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
    ],
    'youtube short link' => [
        'https://youtu.be/dQw4w9WgXcQ',
        SermonMediaPlatform::YouTube,
        'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
    ],
    'vimeo' => [
        'https://vimeo.com/76979871',
        SermonMediaPlatform::Vimeo,
        'https://player.vimeo.com/video/76979871',
    ],
]);

test('supported audio platforms are normalized', function (string $url, SermonMediaPlatform $platform): void {
    $media = app(ExternalMedia::class)->inspect($url);

    expect($media['platform'])->toBe($platform)
        ->and($media['embed_url'])->not->toBeNull()
        ->and(app(ExternalMedia::class)->isEmbeddableUrl($media['embed_url']))->toBeTrue();
})->with([
    ['https://soundcloud.com/forss/flickermood', SermonMediaPlatform::SoundCloud],
    ['https://open.spotify.com/episode/4rOoJ6Egrf8K2IrywzwOMk', SermonMediaPlatform::Spotify],
    ['https://podcasts.apple.com/us/podcast/example/id123456789?i=1000123456789', SermonMediaPlatform::ApplePodcasts],
    ['https://www.mixcloud.com/example/show-name/', SermonMediaPlatform::Mixcloud],
]);

test('unsafe media input is rejected', function (string $value): void {
    expect(fn () => app(ExternalMedia::class)->inspect($value))->toThrow(InvalidArgumentException::class);
})->with([
    'javascript:alert(1)',
    'data:text/html;base64,PHNjcmlwdD4=',
    'file:///etc/passwd',
    '<iframe src="https://youtube.com"></iframe>',
    'https://youtube.com/watch?v=x"><script>alert(1)</script>',
]);

test('unsupported https URLs use an external link fallback', function (): void {
    $media = app(ExternalMedia::class)->inspect('https://media.example.org/sermons/faith');

    expect($media['platform'])->toBe(SermonMediaPlatform::Other)
        ->and($media['embed_url'])->toBeNull()
        ->and(app(ExternalMedia::class)->isEmbeddableUrl('https://media.example.org/embed/faith'))->toBeFalse();
});

test('authorized users can create a draft sermon with series and topics', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::SermonsCreate->value);
    $speaker = Person::factory()->create();
    $series = SermonSeries::factory()->create();
    $topics = Topic::factory()->count(2)->create();

    $sermon = app(SaveSermon::class)->handle(
        $actor,
        validSermonData($speaker, ['sermon_series_id' => $series->id]),
        $topics->modelKeys(),
    );

    expect($sermon->slug)->toBe('walking-by-faith')
        ->and($sermon->speaker->is($speaker))->toBeTrue()
        ->and($sermon->series->is($series))->toBeTrue()
        ->and($sermon->topics)->toHaveCount(2)
        ->and($sermon->status)->toBe(SermonStatus::Draft)
        ->and(ActivityLog::query()->where('event', 'sermon.created')->exists())->toBeTrue();
});

test('the save action derives trusted media fields instead of accepting supplied embed data', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::SermonsCreate->value);
    $speaker = Person::factory()->create();

    $sermon = app(SaveSermon::class)->handle(
        $actor,
        validSermonData($speaker, [
            'media_platform' => SermonMediaPlatform::Other->value,
            'embed_url' => 'https://attacker.example/embed',
            'external_thumbnail_url' => 'https://attacker.example/thumbnail.jpg',
        ]),
        [],
    );

    expect($sermon->media_platform)->toBe(SermonMediaPlatform::YouTube)
        ->and($sermon->embed_url)->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->and($sermon->external_thumbnail_url)->toBe('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg');
});

test('create permission cannot be escalated into publish permission through the save action', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::SermonsCreate->value);
    $speaker = Person::factory()->create();

    expect(fn () => app(SaveSermon::class)->handle(
        $actor,
        validSermonData($speaker, [
            'status' => SermonStatus::Published->value,
            'published_at' => now(),
        ]),
        [],
    ))->toThrow(AuthorizationException::class);

    expect(Sermon::query()->where('title', 'Walking by Faith')->exists())->toBeFalse();
});

test('sermons may exist without a series', function (): void {
    $sermon = Sermon::factory()->create(['sermon_series_id' => null]);

    expect($sermon->series)->toBeNull();
});

test('publishing requires a permission separate from updating', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::SermonsUpdate->value);
    $sermon = Sermon::factory()->create();

    expect(fn () => app(ChangeSermonStatus::class)->publish($actor, $sermon))
        ->toThrow(AuthorizationException::class);

    $actor->givePermissionTo(PermissionName::SermonsPublish->value);
    app(ChangeSermonStatus::class)->publish($actor, $sermon);

    expect($sermon->refresh()->status)->toBe(SermonStatus::Published)
        ->and($sermon->published_at)->not->toBeNull()
        ->and(ActivityLog::query()->where('event', 'sermon.published')->exists())->toBeTrue();
});

test('scheduling requires a future time', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::SermonsSchedule->value);
    $sermon = Sermon::factory()->create();

    expect(fn () => app(ChangeSermonStatus::class)->schedule($actor, $sermon, now()->subMinute()))
        ->toThrow(ValidationException::class);
});

test('duplicating a sermon resets publication history and custom thumbnail', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::SermonsCreate->value);
    $topic = Topic::factory()->create();
    $source = Sermon::factory()->published()->featured()->create(['thumbnail_path' => 'sermons/thumbnails/original.jpg']);
    $source->topics()->attach($topic);

    $duplicate = app(DuplicateSermon::class)->handle($actor, $source);

    expect($duplicate->slug)->not->toBe($source->slug)
        ->and($duplicate->status)->toBe(SermonStatus::Draft)
        ->and($duplicate->published_at)->toBeNull()
        ->and($duplicate->scheduled_at)->toBeNull()
        ->and($duplicate->is_featured)->toBeFalse()
        ->and($duplicate->thumbnail_path)->toBeNull()
        ->and($duplicate->topics)->toHaveCount(1);
});

test('public availability scope enforces every publication state', function (): void {
    $visible = Sermon::factory()->published()->create();
    Sermon::factory()->create();
    Sermon::factory()->create(['status' => SermonStatus::Unpublished]);
    Sermon::factory()->scheduled()->create();
    Sermon::factory()->create(['status' => SermonStatus::Scheduled, 'scheduled_at' => now()->subMinute()]);
    Sermon::factory()->published()->create()->delete();

    expect(Sermon::query()->publiclyAvailable()->pluck('id')->all())
        ->toContain($visible->id)
        ->toHaveCount(2);
});

test('members cannot access sermon administration', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('sermons.index'))->assertForbidden();
});

test('authorized administrators can access sermon administration', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::SermonsView->value);

    $this->actingAs($user)->get(route('sermons.index'))->assertOk();
});

test('public archive and detail pages show only published sermons', function (): void {
    $published = Sermon::factory()->published()->create(['title' => 'Public Faith Message']);
    $draft = Sermon::factory()->create(['title' => 'Private Draft Message']);

    $this->get(route('public.sermons.index'))
        ->assertOk()
        ->assertSee($published->title)
        ->assertDontSee($draft->title);

    $this->get(route('public.sermons.show', $published))
        ->assertOk()
        ->assertSee($published->title)
        ->assertSee('youtube-nocookie.com/embed', false)
        ->assertSee('sandbox=', false)
        ->assertSee('referrerpolicy=', false);

    $this->get(route('public.sermons.show', $draft))->assertNotFound();
});

test('public archive year filtering is database agnostic', function (): void {
    $matching = Sermon::factory()->published()->create([
        'title' => 'Sermon from 2025',
        'sermon_date' => '2025-06-15',
    ]);
    $other = Sermon::factory()->published()->create([
        'title' => 'Sermon from 2024',
        'sermon_date' => '2024-06-15',
    ]);

    $this->get(route('public.sermons.index', ['year' => '2025']))
        ->assertOk()
        ->assertSee($matching->title)
        ->assertDontSee($other->title);
});

test('untrusted hosts are never rendered in an iframe', function (): void {
    $sermon = Sermon::factory()->published()->create([
        'external_media_url' => 'https://media.example.org/watch/faith',
        'media_platform' => SermonMediaPlatform::Other,
        'embed_url' => null,
    ]);

    $html = Blade::render('<x-sermons.media-player :sermon="$sermon" />', ['sermon' => $sermon]);

    expect($html)->not->toContain('<iframe')
        ->and($html)->toContain('rel="noopener noreferrer nofollow"')
        ->and($html)->toContain('https://media.example.org/watch/faith');
});
