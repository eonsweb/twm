<?php

namespace App\View\Composers;

use App\Models\Page;
use App\Models\ServiceSchedule;
use App\Settings\BrandingMedia;
use App\Settings\SettingManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PublicSettingsComposer
{
    public function __construct(
        private readonly SettingManager $settings,
        private readonly BrandingMedia $brandingMedia,
    ) {}

    public function compose(View $view): void
    {
        $publicSettings = $this->settings->publicGroups([
            'general',
            'homepage',
            'church',
            'contact',
            'branding',
            'social',
            'donations',
            'localization',
            'maintenance',
        ]);
        $branding = $publicSettings['branding'] ?? [];

        if ($view->name() === 'components.app-logo') {
            $branding['admin_logo'] = $this->settings->get('branding', 'admin_logo');
        }

        $view->with([
            'publicSettings' => $publicSettings,
            'brandingMediaUrls' => $this->brandingMedia->urls($branding),
        ]);

        if (! is_array(Cache::get('pages.public-navigation'))) {
            Cache::forget('pages.public-navigation');
        }

        $view->with('publicNavigationPages', Page::hydrate(Cache::remember(
            'pages.public-navigation',
            now()->addMinutes(10),
            fn () => Page::query()->publiclyVisible()->where('show_in_navigation', true)->where('is_homepage', false)->orderBy('navigation_order')->orderBy('title')->get(['title', 'slug', 'navigation_label'])->toArray(),
        )));

        if (in_array($view->name(), ['welcome', 'layouts.public'], true)) {
            if (! is_array(Cache::get('system-settings.public.service-schedules'))) {
                Cache::forget('system-settings.public.service-schedules');
            }

            $view->with(
                'publicServiceSchedules',
                ServiceSchedule::hydrate(Cache::rememberForever(
                    'system-settings.public.service-schedules',
                    fn () => ServiceSchedule::query()
                        ->active()
                        ->ordered()
                        ->get()->toArray(),
                )),
            );
        }
    }
}
