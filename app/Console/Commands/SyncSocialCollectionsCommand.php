<?php

namespace App\Console\Commands;

use App\Jobs\SyncUserCollection;
use App\Models\User;
use Illuminate\Console\Command;

class SyncSocialCollectionsCommand extends Command
{
    protected $signature = 'social:sync-collections';

    protected $description = 'Queue a sync for each verified Discogs-connected profile';

    public function handle(): int
    {
        User::query()->whereNotNull('discogs_token')->whereNotNull('email_verified_at')->eachById(fn (User $user) => SyncUserCollection::dispatch($user->id));

        return self::SUCCESS;
    }
}
