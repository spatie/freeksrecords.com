<?php

namespace App\Notifications;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class FriendRequest extends Notification implements ShouldQueue
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
            ->whereNull('accepted_at')->where('requested_by', '!=', $notifiable->id)
            ->where(fn ($query) => $query->where('user_low', $notifiable->id)->orWhere('user_high', $notifiable->id))->exists();
    }

    public function toMail(User $notifiable): MailMessage
    {
        $friendship = Friendship::query()->findOrFail($this->friendshipId);
        $sender = User::query()->findOrFail($friendship->requested_by);
        $expires = now()->addDays(7);
        $links = [];
        foreach (['accept', 'decline'] as $action) {
            $links[$action] = URL::temporarySignedRoute('friendRequest.respond', $expires, [
                'friendship' => $friendship->id, 'recipient' => $notifiable->id, 'action' => $action,
            ]);
        }

        return (new MailMessage)->subject('A friend request on Plaat Club')
            ->markdown('mail.friend-request', ['sender' => $sender, 'links' => $links]);
    }
}
