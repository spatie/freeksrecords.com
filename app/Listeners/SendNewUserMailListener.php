<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\NewUserRegistered;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Notification;

class SendNewUserMailListener
{
    public function send(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        Notification::route('mail', 'freek@spatie.be')->notify(new NewUserRegistered($event->user));
    }
}
