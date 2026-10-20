<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $user_low
 * @property int $user_high
 * @property int $requested_by
 * @property ?string $accepted_at
 */
class Friendship extends Model
{
    protected $guarded = ['id'];

    public function respond(User $recipient, string $action): void
    {
        DB::transaction(function () use ($recipient, $action): void {
            User::query()->whereIn('id', [$this->user_low, $this->user_high])->orderBy('id')->lockForUpdate()->get();
            $pending = self::query()->whereKey($this->id)->lockForUpdate()->first();
            abort_unless($pending && $pending->accepted_at === null
                && $pending->requested_by !== $recipient->id
                && in_array($recipient->id, [$pending->user_low, $pending->user_high], true)
                && in_array($action, ['accept', 'decline'], true), 403);

            if ($action === 'decline') {
                $pending->delete();

                return;
            }

            $pending->update(['accepted_at' => now()]);
            SocialNotification::send(User::query()->findOrFail($pending->requested_by), $recipient, 'friend_accepted');
        });
    }
}
