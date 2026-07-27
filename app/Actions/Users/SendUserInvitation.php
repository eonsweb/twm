<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Password;

class SendUserInvitation
{
    public function handle(User $user): void
    {
        $token = Password::broker()->createToken($user);

        $user->notify(new UserInvitation($token));
    }
}
