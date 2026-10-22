<?php

namespace App\Notifications;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FriendRequestAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $friendshipId)
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
        return $notifiable->hasVerifiedEmail() && Friendship::query()->whereKey($this->friendshipId)
            ->whereNotNull('accepted_at')->where('requested_by', $notifiable->id)->exists();
    }

    public function toMail(User $notifiable): MailMessage
    {
        $friendship = Friendship::query()->findOrFail($this->friendshipId);
        $friend = User::query()->findOrFail($friendship->user_low === $notifiable->id ? $friendship->user_high : $friendship->user_low);

        return (new MailMessage)->subject("{$friend->name} accepted your friend request on Plaat Club")
            ->theme('friend-request-theme')
            ->markdown('mail.friend-request-accepted', [
                'friend' => $friend,
                'url' => $friend->canViewCollection($notifiable) ? route('profile', $friend->handle) : null,
            ]);
    }
}
