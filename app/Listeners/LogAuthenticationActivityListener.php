<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;

class LogAuthenticationActivityListener
{
    public function log(Login|Logout|Registered|Verified|PasswordReset $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        [$name, $description] = match (true) {
            $event instanceof Login => ['login', 'Logged in'],
            $event instanceof Logout => ['logout', 'Logged out'],
            $event instanceof Registered => ['registered', 'Account registered'],
            $event instanceof Verified => ['email_verified', 'Email verified'],
            $event instanceof PasswordReset => ['password_reset', 'Password reset'],
        };

        activity('authentication')->causedBy($event->user)->performedOn($event->user)
            ->event($name)->log($description);
    }
}
