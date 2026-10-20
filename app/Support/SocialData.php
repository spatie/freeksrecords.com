<?php

namespace App\Support;

use App\Models\CollectionRecord;
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
            'records' => $user->records->filter(fn (CollectionRecord $record) => filled($record->payload['cover'] ?? null))
                ->map(self::artwork(...))->values()->all(),
        ];
    }

    /** @return array{cover: string, title: string, url: string} */
    public static function artwork(CollectionRecord $record): array
    {
        return [
            'cover' => $record->payload['cover'],
            'title' => $record->payload['artist'].' — '.$record->payload['displayTitle'],
            'url' => route('shelf.record', $record, false),
        ];
    }

    /**
     * @param  list<string>  $excludeCovers
     * @return list<array{cover: string, title: string, url: string}>
     */
    public static function publicArtwork(array $excludeCovers = []): array
    {
        return array_values(CollectionRecord::visibleTo(null)
            ->whereNotNull('payload->cover')->where('payload->cover', '!=', '')
            ->whereNotIn('payload->cover', $excludeCovers)
            ->inRandomOrder()->limit(6)->get(['id', 'payload'])
            ->unique('payload.cover')->map(self::artwork(...))->all());
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
