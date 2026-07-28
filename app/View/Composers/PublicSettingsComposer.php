<?php

namespace App\View\Composers;

use App\Models\ServiceSchedule;
use App\Settings\SettingManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PublicSettingsComposer
{
    public function __construct(private readonly SettingManager $settings) {}

    public function compose(View $view): void
    {
        $view->with('publicSettings', $this->settings->publicGroups([
            'general',
            'church',
            'contact',
            'branding',
            'social',
            'donations',
            'localization',
            'maintenance',
        ]));

        if ($view->name() === 'welcome') {
            $view->with(
                'publicServiceSchedules',
                Cache::rememberForever(
                    'system-settings.public.service-schedules',
                    fn () => ServiceSchedule::query()
                        ->where('is_active', true)
                        ->orderBy('display_order')
                        ->orderBy('id')
                        ->get(),
                ),
            );
        }
    }
}
