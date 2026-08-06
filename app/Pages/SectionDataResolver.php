<?php

namespace App\Pages;

use App\Models\Book;
use App\Models\Event;
use App\Models\Ministry;
use App\Models\PageSection;
use App\Models\Person;
use App\Models\Post;
use App\Models\Sermon;
use App\Models\ServiceSchedule;
use App\Settings\SettingManager;

class SectionDataResolver
{
    public function __construct(private readonly SettingManager $settings) {}

    /** @return array<string, mixed> */
    public function resolve(PageSection $section): array
    {
        $limit = min(24, max(1, (int) data_get($section->settings, 'limit', 6)));

        return match ($section->section_type->value) {
            'featured-sermons' => ['items' => Sermon::query()->publiclyAvailable()->latest('sermon_date')->limit($limit)->get()],
            'upcoming-events' => ['items' => Event::query()->where('status', 'published')->where('starts_at', '>=', now())->orderBy('starts_at')->limit($limit)->get()],
            'latest-posts' => ['items' => Post::query()->publiclyVisible()->latest('published_at')->limit($limit)->get()],
            'ministries-grid' => ['items' => Ministry::query()->where('status', 'published')->orderBy('display_order')->limit($limit)->get()],
            'leadership-grid' => ['items' => Person::query()->where('is_active', true)->where('is_public', true)->limit($limit)->get()],
            'books-grid' => ['items' => Book::query()->where('status', 'published')->latest('published_at')->limit($limit)->get()],
            'service-times' => ['items' => ServiceSchedule::query()->where('is_active', true)->orderBy('display_order')->get()],
            'church-locations', 'contact-details' => ['settings' => $this->settings->publicGroups(['church', 'contact'])],
            'livestream' => ['settings' => $this->settings->publicGroups(['social'])],
            default => [],
        };
    }
}
