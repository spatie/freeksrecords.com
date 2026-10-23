<?php

namespace App\Listeners;

use App\Actions\Social\UpdateFriendshipAction;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;

class FulfillInvitationsListener
{
    public function fulfill(Registered|Verified $event): void
    {
        $recipient = $event->user;
        if (! $recipient instanceof User || ! $recipient->hasVerifiedEmail()) {
            return;
        }

        Invitation::query()->where('email', mb_strtolower(trim($recipient->email)))
            ->whereNull('fulfilled_at')->where('expires_at', '>', now())->eachById(function (Invitation $invitation) use ($recipient): void {
                DB::transaction(function () use ($invitation, $recipient): void {
                    $invitation = Invitation::query()->lockForUpdate()->find($invitation->id);
                    if (! $invitation || $invitation->fulfilled_at || $invitation->expires_at->isPast()) {
                        return;
                    }

                    if ($invitation->inviter_id !== $recipient->id) {
                        app(UpdateFriendshipAction::class)->execute($invitation->inviter, $recipient, 'request');
                    }

                    $invitation->update(['fulfilled_at' => now()]);
                }, 3);
            });
    }
}
