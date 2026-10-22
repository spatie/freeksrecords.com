<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $invitationId, public string $token)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return Invitation::query()->whereKey($this->invitationId)->whereNull('fulfilled_at')
            ->where('expires_at', '>', now())->where('token_hash', hash('sha256', $this->token))->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invitation = Invitation::query()->with('inviter')->findOrFail($this->invitationId);

        return (new MailMessage)->subject("{$invitation->inviter->name} invited you to Plaat Club")
            ->markdown('mail.invitation', [
                'sender' => $invitation->inviter,
                'url' => route('invitations.show', $this->token),
            ]);
    }
}
