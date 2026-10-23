<?php

namespace App\Actions\Discogs;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class DisconnectDiscogsAccountAction
{
    public function execute(User $user): void
    {
        $lock = Cache::lock('discogs-sync-'.$user->id, 7500);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['discogs' => 'A sync is running. Please wait before disconnecting.']);
        }

        try {
            $connected = $user->discogs_token !== null;
            $user->update(['discogs_token' => null, 'discogs_secret' => null, 'discogs_sync_state' => null]);
            if ($connected) {
                activity('discogs')->causedBy($user)->performedOn($user)
                    ->event('discogs_disconnected')->log('Discogs account disconnected');
            }
        } finally {
            $lock->release();
        }
    }
}
