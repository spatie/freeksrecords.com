<?php

namespace App\Actions;

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
            $user->update(['discogs_token' => null, 'discogs_secret' => null]);
        } finally {
            $lock->release();
        }
    }
}
