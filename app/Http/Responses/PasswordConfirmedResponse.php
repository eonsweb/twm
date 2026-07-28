<?php

namespace App\Http\Responses;

use App\Activity\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse as PasswordConfirmedResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class PasswordConfirmedResponse implements PasswordConfirmedResponseContract
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function toResponse($request): Response
    {
        $this->logger->log(
            logName: 'authentication',
            event: 'password.confirmed',
            description: 'Confirmed their password for a sensitive action.',
            subject: $request->user(),
            causer: $request->user(),
        );

        return $request->wantsJson()
            ? new JsonResponse('', 201)
            : redirect()->intended(Fortify::redirects('password-confirmation'));
    }
}
