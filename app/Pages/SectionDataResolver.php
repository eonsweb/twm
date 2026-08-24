<?php

namespace App\Pages;

use App\EventStatus;
use App\Models\Book;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Ministry;
use App\Models\PageSection;
use App\Models\Person;
use App\Models\Post;
use App\Models\Sermon;
use App\Models\ServiceSchedule;
use App\Settings\SettingManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SectionDataResolver
{
    public function __construct(private readonly SettingManager $settings) {}

    /** @return array<string, mixed> */
    public function resolve(PageSection $section): array
    {
        $limit = min(24, max(1, (int) data_get($section->settings, 'limit', 6)));

        return match ($section->section_type->value) {
            'featured-sermons' => $this->featuredSermon($section),
            'upcoming-events' => $this->events($section, $limit),
            'latest-posts' => ['items' => Post::query()->publiclyVisible()->latest('published_at')->limit($limit)->get()],
            'ministries-grid' => ['items' => Ministry::query()->active()->published()->ordered()->get([
                'id', 'name', 'slug', 'featured_image', 'logo', 'display_order',
            ])],
            'leadership-grid' => ['items' => Person::query()->where('is_active', true)->where('is_public', true)->limit($limit)->get()],
            'featured-book' => ['book' => $this->featuredBook()],
            'books-grid' => ['items' => Book::query()->where('status', 'published')->latest('published_at')->limit($limit)->get()],
            'service-times' => ['items' => ServiceSchedule::query()->active()->ordered()->get()],
            'welcome' => $this->welcome(),
            'welcome-upcoming-event' => $this->welcomeUpcomingEvent(),
            'church-locations', 'contact-details' => ['settings' => $this->settings->publicGroups(['church', 'contact'])],
            'livestream' => ['settings' => $this->settings->publicGroups(['social'])],
            default => [],
        };
    }

    /** @return array{items: Collection<int, Sermon>} */
    private function featuredSermon(PageSection $section): array
    {
        $speakerId = data_get($section->settings, 'speaker_id');
        $sermon = Sermon::query()
            ->publiclyAvailable()
            ->when(filled($speakerId), fn (Builder $query): Builder => $query->where('speaker_id', (int) $speakerId))
            ->with('speaker:id,title,first_name,middle_name,last_name,slug')
            ->orderByDesc('is_featured')
            ->latest('sermon_date')
            ->latest('published_at')
            ->first([
                'id', 'title', 'slug', 'summary', 'sermon_date', 'external_media_url',
                'media_platform', 'media_type', 'embed_url', 'thumbnail_path',
                'external_thumbnail_url', 'speaker_id', 'published_at', 'is_featured',
            ]);

        return ['items' => collect([$sermon])->filter()->values()];
    }

    /** @return array<string, mixed> */
    private function welcomeUpcomingEvent(): array
    {
        $welcome = $this->welcome();
        $settings = $welcome['settings'];
        $leader = $welcome['leader'];
        $sermon = Sermon::query()
            ->publiclyAvailable()
            ->with('speaker:id,title,first_name,middle_name,last_name,slug')
            ->latest('sermon_date')
            ->latest('published_at')
            ->first([
                'id', 'title', 'slug', 'sermon_date', 'thumbnail_path', 'external_thumbnail_url',
                'speaker_id', 'published_at',
            ]);
        $events = Event::query()
            ->active()
            ->published()
            ->upcoming()
            ->where('status', EventStatus::Published)
            ->with('eventType:id,name')
            ->oldest('starts_at')
            ->limit(3)
            ->get([
                'id', 'event_type_id', 'title', 'slug', 'starts_at', 'ends_at', 'timezone',
                'schedule_type', 'is_all_day', 'is_recurring', 'recurrence_interval',
                'recurrence_days', 'recurrence_week_of_month', 'recurrence_month',
                'recurrence_day_of_month', 'recurrence_end_date',
            ]);

        return compact('settings', 'leader', 'sermon', 'events');
    }

    /** @return array<string, mixed> */
    private function welcome(): array
    {
        $settings = $this->settings->publicGroups(['homepage', 'church']);
        $selectedLeaderId = data_get($settings, 'homepage.welcome_leader_id');
        $leader = Person::query()
            ->active()
            ->where('is_public', true)
            ->with(['primaryLeadershipAssignment' => fn ($query) => $query
                ->select(['id', 'person_id', 'leadership_position_id', 'display_title', 'sort_order'])
                ->with('position:id,name')])
            ->when(filled($selectedLeaderId), fn (Builder $query): Builder => $query->whereKey((int) $selectedLeaderId))
            ->when(blank($selectedLeaderId), fn (Builder $query): Builder => $query
                ->whereHas('primaryLeadershipAssignment')
                ->orderBy('id'))
            ->first(['id', 'title', 'first_name', 'middle_name', 'last_name', 'slug', 'photo_path']);

        return compact('settings', 'leader');
    }

    private function featuredBook(): ?Book
    {
        return Book::query()
            ->published()
            ->featured()
            ->available()
            ->with([
                'cover:id,disk,path,media_type,visibility,status,alt_text,width,height',
                'audioSample:id,disk,path,mime_type,media_type,visibility,status',
            ])
            ->latest('published_at')
            ->first([
                'id', 'title', 'slug', 'author_name', 'short_description', 'purchase_url',
                'download_url', 'media_id', 'audio_sample_media_id', 'is_featured', 'is_free',
                'availability_status',
            ]);
    }

    /** @return array<string, mixed> */
    private function events(PageSection $section, int $limit): array
    {
        $eventTypeId = data_get($section->settings, 'event_type_id');
        $eventType = filled($eventTypeId) ? EventType::query()->find((int) $eventTypeId) : null;
        $eventLimit = min(4, $limit);
        $eventColumns = [
            'id', 'event_type_id', 'featured_image_id', 'title', 'slug', 'short_description',
            'description', 'featured_image', 'icon', 'starts_at', 'ends_at', 'timezone',
            'schedule_type', 'is_all_day', 'is_recurring', 'recurrence_interval',
            'recurrence_days', 'recurrence_week_of_month', 'recurrence_month',
            'recurrence_day_of_month', 'recurrence_end_date', 'is_featured',
        ];
        $eventQuery = Event::query()
            ->active()
            ->published()
            ->upcoming()
            ->where('status', EventStatus::Published)
            ->when($eventType !== null, fn ($query) => $query->whereBelongsTo($eventType))
            ->with(['eventType:id,name,slug,icon,color', 'featuredImage:id,disk,path,media_type,visibility,status']);
        $featuredEvent = (clone $eventQuery)
            ->featured()
            ->oldest('starts_at')
            ->first($eventColumns);
        $remainingEvents = (clone $eventQuery)
            ->when($featuredEvent !== null, fn (Builder $query): Builder => $query->whereKeyNot($featuredEvent->getKey()))
            ->oldest('starts_at')
            ->limit($eventLimit - ($featuredEvent === null ? 0 : 1))
            ->get($eventColumns);
        $events = collect([$featuredEvent])
            ->filter()
            ->concat($remainingEvents)
            ->take($eventLimit)
            ->values();

        return [
            'items' => $events,
            'eventType' => $eventType,
            'viewAllUrl' => route('public.events.index', array_filter(['type' => $eventType?->slug])),
        ];
    }
}
