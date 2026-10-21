<?php

namespace App\Console\Commands;

use App\Actions\SendFriendsDigestAction;
use App\Models\User;
use Illuminate\Console\Command;

class SendFriendsDigestCommand extends Command
{
    protected $signature = 'social:send-digests {--preview-for= : Local preview for one recipient, without advancing their weekly checkpoint}';

    protected $description = 'Send the latest completed Monday-to-Monday friends digest, checking current access';

    public function handle(SendFriendsDigestAction $sendDigest): int
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
        User::query()->when($preview, fn ($query) => $query->whereKey($preview))->whereNotNull('email_verified_at')->where('digest_enabled', true)->eachById(function (User $candidate) use ($sendDigest, $end, $preview): void {
            $sendDigest->execute($candidate, $end, (bool) $preview);
        });
        $this->info('Friends digests processed.');

        return self::SUCCESS;
    }
}
