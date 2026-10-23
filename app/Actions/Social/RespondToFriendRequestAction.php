<?php

namespace App\Actions\Social;

use App\Models\Friendship;
use App\Models\SocialNotification;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use Illuminate\Support\Facades\DB;

class RespondToFriendRequestAction
{
    public function execute(Friendship $friendship, User $recipient, string $action): void
    {
        DB::transaction(function () use ($friendship, $recipient, $action): void {
            User::query()->whereIn('id', [$friendship->user_low, $friendship->user_high])->orderBy('id')->lockForUpdate()->get();
            $pending = Friendship::query()->whereKey($friendship->id)->lockForUpdate()->first();
            abort_unless($pending && $pending->accepted_at === null
                && $pending->requested_by !== $recipient->id
                && in_array($recipient->id, [$pending->user_low, $pending->user_high], true)
                && in_array($action, ['accept', 'decline'], true), 403);

            $requester = User::query()->findOrFail($pending->requested_by);
            activity('social')->causedBy($recipient)->performedOn($requester)
                ->event($action === 'decline' ? 'friend_request_declined' : 'friend_request_accepted')
                ->log($action === 'decline' ? 'Friend request declined' : 'Friend request accepted');

            if ($action === 'decline') {
                $pending->delete();

                return;
            }

            $pending->update(['accepted_at' => now()]);
            SocialNotification::send($requester, $recipient, 'friend_accepted');
            $requester->notify(new FriendRequestAccepted($pending->id));
        });
    }
}
