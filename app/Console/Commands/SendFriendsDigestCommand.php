<?php

namespace App\Console\Commands;

use App\Mail\FriendsWeeklyDigest;
use App\Models\CollectionRecord;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendFriendsDigestCommand extends Command
{
    protected $signature = 'social:send-digests {--preview-for= : Local preview for one recipient, without advancing their weekly checkpoint}';

    protected $description = 'Send the latest completed Monday-to-Monday friends digest, checking current access';

    public function handle(): int
    {
        $preview = $this->option('preview-for');
        if ($preview && ! app()->environment('local')) {
            $this->error('Digest previews are restricted to the local environment.');

            return self::FAILURE;
        }
        $end = now()->timezone('Europe/Brussels')->startOfWeek()->setTime(9, 0);
        if ($end->isFuture()) {
            $end = $end->subWeek();
        }
        if ($preview) {
            $end = now()->timezone('Europe/Brussels');
        }
        $week = $end->toDateString();
        User::query()->when($preview, fn ($query) => $query->whereKey($preview))->whereNotNull('email_verified_at')->where('digest_enabled', true)->eachById(function (User $candidate) use ($end, $week, $preview): void {
            Cache::lock('digest-'.$candidate->id, 300)->get(function () use ($candidate, $end, $week, $preview): void {
                $user = $candidate->fresh();
                if (! $user->digest_enabled || ! $user->email_verified_at || (! $preview && $user->digest_week === $week)) {
                    return;
                }
                // Deliberately synchronous: no queued/rendered payload can outlive its privacy check.
                $records = CollectionRecord::friendActivity($user)->with('user')
                    ->where('added_at', '>=', $end->subWeek()->utc()->toDateTimeString())
                    ->where('added_at', '<', $end->utc()->toDateTimeString())->get();
                if ($records->isNotEmpty()) {
                    Mail::to($user->email)->send(new FriendsWeeklyDigest($records));
                }
                if (! $preview) {
                    $user->update(['digest_week' => $week]);
                }
            });
        });

        return self::SUCCESS;
    }
}
