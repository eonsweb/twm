<?php

namespace App\ViewModels;

use App\EventStatus;
use App\Models\Book;
use App\Models\Event;
use App\Models\Ministry;
use App\Models\Person;
use App\Models\Sermon;
use App\Models\ServiceSchedule;
use App\Settings\BrandingMedia;
use App\Settings\SettingManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HomepageContent
{
    /** @var list<string> */
    private const DEFAULT_SECTIONS = [
        'services', 'welcome_upcoming_event', 'ministries', 'calls_to_action', 'featured_book', 'testimonials',
    ];

    public function __construct(
        private readonly SettingManager $settings,
        private readonly BrandingMedia $brandingMedia,
    ) {}

    /**
     * @return array{
     *   settings: array<string, array<string, mixed>>,
     *   enabledSections: list<string>,
     *   heroImageUrl: string|null,
     *   logoUrl: string|null,
     *   socialImageUrl: string|null,
     *   serviceSchedules: Collection<int, ServiceSchedule>,
     *   welcomeLeader: Person|null,
     *   latestSermon: Sermon|null,
     *   upcomingEvents: Collection<int, Event>,
     *   ministries: Collection<int, Ministry>,
     *   featuredBook: Book|null,
     *   testimonials: list<array{quote: string, name: string, role: string|null, portrait_url: string|null}>
     * }
     */
    public function build(): array
    {
        $settings = $this->settings->publicGroups([
            'general', 'homepage', 'church', 'contact', 'branding', 'social', 'donations',
        ]);
        $brandingUrls = $this->brandingMedia->urls($settings['branding'] ?? []);

        return [
            'settings' => $settings,
            'enabledSections' => $this->enabledSections($settings['homepage'] ?? []),
            'heroImageUrl' => $brandingUrls['homepage_hero_image'] ?? null,
            'logoUrl' => $brandingUrls['primary_logo'] ?? null,
            'socialImageUrl' => $brandingUrls['social_share_image'] ?? null,
            'serviceSchedules' => $this->serviceSchedules(),
            'welcomeLeader' => $this->welcomeLeader($settings['homepage'] ?? []),
            'latestSermon' => $this->latestSermon(),
            'upcomingEvents' => $this->upcomingEvents(),
            'ministries' => $this->ministries(),
            'featuredBook' => $this->featuredBook(),
            'testimonials' => $this->testimonials($settings['homepage'] ?? []),
        ];
    }

    /** @return Collection<int, ServiceSchedule> */
    private function serviceSchedules(): Collection
    {
        return ServiceSchedule::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'day_of_week', 'start_time', 'end_time', 'location']);
    }

    /** @param array<string, mixed> $homepage */
    private function welcomeLeader(array $homepage): ?Person
    {
        $query = Person::query()
            ->active()
            ->where('is_public', true)
            ->with(['primaryLeadershipAssignment' => fn ($query) => $query
                ->select(['id', 'person_id', 'leadership_position_id', 'display_title', 'sort_order'])
                ->with('position:id,name')]);

        $selectedId = data_get($homepage, 'welcome_leader_id');

        return $query
            ->when(filled($selectedId), fn (Builder $query): Builder => $query->whereKey((int) $selectedId))
            ->when(blank($selectedId), fn (Builder $query): Builder => $query
                ->whereHas('primaryLeadershipAssignment')
                ->orderBy('id'))
            ->first(['id', 'title', 'first_name', 'middle_name', 'last_name', 'slug', 'photo_path', 'short_bio']);
    }

    private function latestSermon(): ?Sermon
    {
        return Sermon::query()
            ->publiclyAvailable()
            ->with('speaker:id,title,first_name,middle_name,last_name,slug')
            ->orderByDesc('is_featured')
            ->latest('sermon_date')
            ->latest('published_at')
            ->first([
                'id', 'title', 'slug', 'summary', 'sermon_date', 'external_media_url',
                'media_platform', 'media_type', 'embed_url', 'thumbnail_path',
                'external_thumbnail_url', 'speaker_id', 'published_at', 'is_featured',
            ]);
    }

    /** @return Collection<int, Event> */
    private function upcomingEvents(): Collection
    {
        return Event::query()
            ->active()
            ->published()
            ->upcoming()
            ->where('status', EventStatus::Published)
            ->with(['eventType:id,name,icon', 'featuredImage:id,disk,path,media_type,visibility,status'])
            ->oldest('starts_at')
            ->orderBy('sort_order')
            ->limit(3)
            ->get([
                'id', 'event_type_id', 'featured_image_id', 'title', 'slug', 'icon', 'starts_at', 'ends_at',
                'timezone', 'schedule_type', 'is_all_day', 'is_recurring', 'recurrence_interval',
                'recurrence_days', 'recurrence_week_of_month', 'recurrence_month',
                'recurrence_day_of_month', 'recurrence_end_date', 'sort_order',
            ]);
    }

    /** @return Collection<int, Ministry> */
    private function ministries(): Collection
    {
        return Ministry::query()
            ->active()
            ->published()
            ->orderByDesc('is_featured')
            ->ordered()
            ->get(['id', 'name', 'slug', 'short_description', 'featured_image', 'logo', 'is_featured', 'display_order']);
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
                'id', 'title', 'slug', 'author_name', 'short_description', 'price', 'currency',
                'purchase_url', 'download_url', 'media_id', 'audio_sample_media_id', 'is_featured',
                'is_free', 'availability_status',
            ]);
    }

    /**
     * @param  array<string, mixed>  $homepage
     * @return list<string>
     */
    private function enabledSections(array $homepage): array
    {
        $sections = data_get($homepage, 'enabled_sections', self::DEFAULT_SECTIONS);

        return is_array($sections)
            ? array_values(array_filter($sections, 'is_string'))
            : [];
    }

    /**
     * @param  array<string, mixed>  $homepage
     * @return list<array{quote: string, name: string, role: string|null, portrait_url: string|null}>
     */
    private function testimonials(array $homepage): array
    {
        $items = data_get($homepage, 'testimonials', []);

        if (! is_array($items)) {
            return [];
        }

        $testimonials = [];

        foreach ($items as $item) {
            if (! is_array($item)
                || ($item['enabled'] ?? true) !== true
                || blank($item['quote'] ?? null)
                || blank($item['name'] ?? null)
            ) {
                continue;
            }

            $testimonials[] = [
                'quote' => (string) $item['quote'],
                'name' => (string) $item['name'],
                'role' => filled($item['role'] ?? null) ? (string) $item['role'] : null,
                'portrait_url' => $this->safeExternalUrl($item['portrait_url'] ?? null),
            ];

            if (count($testimonials) === 10) {
                break;
            }
        }

        return $testimonials;
    }

    private function safeExternalUrl(mixed $url): ?string
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null;
    }
}
