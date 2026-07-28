<?php

namespace App\Activity;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class RequestMetadata
{
    /**
     * @return array<string, mixed>
     */
    public function capture(?string $origin = null): array
    {
        $request = app()->bound('request') ? request() : null;

        if ($request instanceof Request && $request->route() !== null) {

            return [
                'request_id' => Context::get('request_id'),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'request_url' => $request->url(),
                'http_method' => $request->method(),
                'route_name' => $request->route()->getName(),
                'origin' => $origin ?? ($request->user() === null ? 'guest' : 'web'),
            ];
        }

        return [
            'request_id' => Context::get('request_id'),
            'origin' => $origin ?? (app()->runningInConsole() ? 'console' : 'system'),
        ];
    }
}
