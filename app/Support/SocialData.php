<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;

class SocialData
{
    /** @return array<string, mixed> */
    public static function person(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'handle' => $user->handle,
            'profileUrl' => route('profile', $user->handle, false),
        ];
    }

    /** @return array<string, mixed> */
    public static function collector(User $user): array
    {
        return [
            ...self::person($user),
            'recordCount' => $user->records_count,
            'covers' => $user->records->pluck('payload.cover')->filter()->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function viewer(User $user): array
    {
        return [
            ...self::person($user),
            'email' => $user->email,
            'verified' => $user->hasVerifiedEmail(),
            'visibility' => $user->visibility,
            'legacyOwner' => $user->legacy_owner ?? false,
            'commentsEnabled' => $user->comments_enabled ?? true,
            'digestEnabled' => $user->digest_enabled ?? true,
            'googleConnected' => filled($user->google_id),
            'discogsConnected' => filled($user->discogs_token),
            'discogsUsername' => $user->discogs_username,
            'importedAt' => $user->collection_imported_at?->toIso8601String(),
            'lastSyncedAt' => $user->last_synced_at?->toIso8601String(),
            'syncStatus' => $user->sync_status,
            'collectionCount' => $user->records()->count(),
        ];
    }

    /**
     * @param  Paginator<int, mixed>  $paginator
     * @param  array<int, mixed>  $data
     * @return array<string, mixed>
     */
    public static function pagination(Paginator $paginator, array $data): array
    {
        return [
            'data' => $data,
            'prev_page_url' => $paginator->previousPageUrl(),
            'next_page_url' => $paginator->nextPageUrl(),
        ];
    }
}
