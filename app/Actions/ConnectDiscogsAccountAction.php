<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ConnectDiscogsAccountAction
{
    public function execute(User $user, string $username, string $token, string $secret): void
    {
        if ($user->legacy_owner && $username !== config('services.discogs.username')) {
            throw new RuntimeException('Wrong legacy owner');
        }

        Cache::lock('discogs-sync-'.$user->id, 7500)->block(3, function () use ($user, $username, $token, $secret): void {
            $current = $user->fresh();
            // Reconnecting must never mix two different people's collections.
            if ($current->discogs_username && $current->discogs_username !== $username) {
                throw new RuntimeException('Different Discogs identity');
            }

            $current->update([
                'discogs_username' => $username,
                'discogs_token' => $token,
                'discogs_secret' => $secret,
                'discogs_sync_state' => null,
            ]);
        });
    }
}
