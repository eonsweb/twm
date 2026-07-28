<?php

namespace App\Http\Responses;

use App\Activity\ActivityLogger;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordConfirmationResponse as FailedPasswordConfirmationResponseContract;
use Symfony\Component\HttpFoundation\Response;

class FailedPasswordConfirmationResponse implements FailedPasswordConfirmationResponseContract
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function toResponse($request): Response
    {
        $this->logger->log(
            logName: 'authentication',
            event: 'password.confirmation_failed',
            description: 'Failed password confirmation.',
            subject: $request->user(),
            causer: $request->user(),
            status: 'failure',
            failureReason: 'Password confirmation failed.',
        );

        $message = __('The provided password was incorrect.');

        if ($request->wantsJson()) {
            throw ValidationException::withMessages(['password' => [$message]]);
        }

        return back()->withErrors(['password' => $message]);
    }
}
