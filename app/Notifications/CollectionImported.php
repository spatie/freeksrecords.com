<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CollectionImported extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $recordCount)
    {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $notifiable->hasVerifiedEmail();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your collection is ready. Let’s play!')
            ->theme('collection-imported-theme')
            ->markdown('mail.collection-imported', [
                'collector' => $notifiable,
                'recordCount' => number_format($this->recordCount),
                'url' => route('profile', $notifiable->handle),
            ]);
    }
}
