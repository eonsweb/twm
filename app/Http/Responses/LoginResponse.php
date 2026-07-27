<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the login.
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return response()->json([
                'two_factor' => false,
                'must_change_password' => (bool) $request->user()?->must_change_password,
            ]);
        }

        if ($request->user()?->must_change_password === true) {
            return redirect()->route('password.change.required');
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
