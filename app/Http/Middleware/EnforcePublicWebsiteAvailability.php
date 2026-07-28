<?php

namespace App\Http\Middleware;

use App\RoleName;
use App\Settings\SettingManager;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePublicWebsiteAvailability
{
    public function __construct(private readonly SettingManager $settings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isUnavailable() || $this->mayBypass($request)) {
            return $next($request);
        }

        return response()
            ->view('maintenance', [
                'maintenance' => $this->settings->publicGroup('maintenance'),
                'church' => $this->settings->publicGroup('church'),
            ], Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Retry-After', '3600');
    }

    private function isUnavailable(): bool
    {
        if (! $this->settings->get('general', 'website_enabled', true)) {
            return true;
        }

        if (! $this->settings->get('maintenance', 'enabled', false)) {
            return false;
        }

        $now = CarbonImmutable::now();
        $startsAt = $this->date('starts_at');
        $endsAt = $this->date('ends_at');

        return ($startsAt === null || $now->greaterThanOrEqualTo($startsAt))
            && ($endsAt === null || $now->lessThan($endsAt));
    }

    private function mayBypass(Request $request): bool
    {
        return $this->settings->get('maintenance', 'allow_admin_access', true)
            && $request->user()?->hasRole(RoleName::SuperAdmin) === true;
    }

    private function date(string $key): ?CarbonImmutable
    {
        $value = $this->settings->get('maintenance', $key);

        return filled($value) ? CarbonImmutable::parse($value) : null;
    }
}
