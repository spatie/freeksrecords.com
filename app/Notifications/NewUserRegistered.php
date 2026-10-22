<?php

namespace App\Notifications;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserRegistered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A new collector joined Plaat Club')
            ->theme('friend-request-theme')
            ->markdown('mail.new-user-registered', [
                'user' => $this->user,
                'url' => UserResource::getUrl('view', ['record' => $this->user], panel: 'admin'),
            ]);
    }
}
