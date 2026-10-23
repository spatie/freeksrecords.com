<?php

namespace App\Actions\Social;

use App\Models\Friendship;
use App\Models\SocialNotification;
use App\Models\User;
use App\Notifications\FriendRequest;
use Illuminate\Support\Facades\DB;

class UpdateFriendshipAction
{
    public function __construct(protected RespondToFriendRequestAction $respondToFriendRequest) {}

    public function execute(User $viewer, User $user, string $action): void
    {
        abort_if($viewer->id === $user->id, 422);

        DB::transaction(function () use ($user, $viewer, $action): void {
            // Serializes both directions, including two simultaneous first requests.
            User::query()->whereIn('id', [$viewer->id, $user->id])->orderBy('id')->lockForUpdate()->get();
            $pair = ['user_low' => min($viewer->id, $user->id), 'user_high' => max($viewer->id, $user->id)];
            $friendship = Friendship::query()->where($pair)->first();

            if ($action === 'request') {
                if ($friendship === null) {
                    $friendship = Friendship::query()->create([...$pair, 'requested_by' => $viewer->id]);
                    activity('social')->causedBy($viewer)->performedOn($user)
                        ->event('friend_request_sent')->log('Friend request sent');
                    SocialNotification::send($user, $viewer, 'friend_request');
                    $user->notify(new FriendRequest($friendship->id));
                }

                return;
            }

            if (in_array($action, ['accept', 'decline'], true)) {
                abort_unless($friendship !== null, $action === 'accept' ? 403 : 404);
                abort_unless(! $friendship->accepted_at && $friendship->requested_by !== $viewer->id, 403);
                $this->respondToFriendRequest->execute($friendship, $viewer, $action);

                return;
            }

            abort_unless($friendship !== null, 404);
            abort_unless(match ($action) {
                'unfriend' => $friendship->accepted_at !== null,
                'cancel' => $friendship->accepted_at === null && $friendship->requested_by === $viewer->id,
                default => false,
            }, 403);
            $friendship->delete();
            activity('social')->causedBy($viewer)->performedOn($user)
                ->event($action === 'cancel' ? 'friend_request_cancelled' : 'unfriended')
                ->log($action === 'cancel' ? 'Friend request cancelled' : 'Friend removed');
        });
    }
}
