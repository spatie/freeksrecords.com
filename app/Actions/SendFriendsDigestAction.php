<?php

namespace App\Actions;

use App\Mail\FriendsWeeklyDigest;
use App\Models\CollectionRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendFriendsDigestAction
{
    public function execute(User $candidate, CarbonImmutable $end, bool $preview = false): void
    {
        $week = $end->toDateString();
        $user = $candidate->fresh();
        if (! $user->digest_enabled || ! $user->email_verified_at || (! $preview && $user->digest_week === $week)) {
            return;
        }

        $records = CollectionRecord::friendActivity($user)->with('user')
            ->where('added_at', '>=', $end->subWeek()->utc()->toDateTimeString())
            ->where('added_at', '<', $end->utc()->toDateTimeString())->get();
        $mail = (new FriendsWeeklyDigest($records))->prepareCovers();

        // Downloads must not consume the send lock's lifetime; recheck privacy and delivery inside it.
        Cache::lock('digest-'.$candidate->id, 300)->get(function () use ($candidate, $mail, $week, $preview): void {
            $user = $candidate->fresh();
            if (! $user->digest_enabled || ! $user->email_verified_at || (! $preview && $user->digest_week === $week)) {
                return;
            }

            // Deliberately synchronous: no queued/rendered payload can outlive its privacy check.
            $mail->records = CollectionRecord::friendActivity($user)->with('user')->whereKey($mail->records->modelKeys())->get();
            if ($mail->records->isNotEmpty()) {
                Mail::to($user->email)->send($mail);
            }

            if (! $preview) {
                $user->update(['digest_week' => $week]);
            }
        });
    }
}
