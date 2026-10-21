<?php

namespace App\Support;

use App\Models\CollectionRecord;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SocialData
{
    /** @return array<string, mixed> */
    public static function record(Request $request, CollectionRecord $record): array
    {
        abort_unless($record->user->canViewCollection($request->user()), 404);

        $collectionUrl = route('profile', $record->user->handle);
        $browse = $request->query('browse');
        if (is_string($browse) && strlen($browse) <= 1000 && $browse !== '') {
            $collectionUrl .= '#'.$browse;
        }

        $owner = $request->user()?->id === $record->user_id;
        $comments = $record->comments()->with('user')->oldest('id')->paginate(30, ['*'], 'commentsPage')->withQueryString();
        $payload = Arr::only($record->payload, [
            'id', 'instanceId', 'artist', 'artists', 'title', 'displayTitle', 'edition',
            'year', 'originalYear', 'originalReleaseDate', 'pressingReleaseDate', 'addedAt',
            'label', 'catalogNumber', 'format', 'mediaFormats', 'genres', 'styles', 'cover',
            'discogsUrl', 'appleMusicUrl', 'appleMusicSearch', 'spotifyUrl', 'spotifySearch', 'trackSource',
        ]);
        if (filled($payload['cover'] ?? null) && $payload['cover'] !== '/images/record-placeholder.svg') {
            $payload['cover'] = route('shelf.cover', $record, false);
        }
        $payload['tracks'] = array_map(fn (array $track) => Arr::only($track, [
            'position', 'title', 'artist', 'duration', 'appleMusicUrl', 'appleMusicSearch', 'spotifyUrl', 'spotifySearch',
        ]), $record->payload['tracks'] ?? []);

        return [
            'collectionUrl' => $collectionUrl,
            'record' => [
                'id' => $record->id,
                'owner' => self::person($record->user),
                'payload' => $payload,
                'formats' => $record->formats(),
                'addedLabel' => $record->added_at->timezone('Europe/Brussels')->format('j F Y'),
                'sharedNote' => $record->shared_note,
                ...($owner ? ['privateNote' => $record->private_note] : []),
                'canEditNotes' => $owner,
                'commentsEnabled' => $record->user->comments_enabled,
            ],
            'comments' => self::pagination($comments, $comments->getCollection()->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'user' => self::person($comment->user),
                'canDelete' => in_array($request->user()?->id, [$comment->user_id, $record->user_id], true),
            ])->all()),
            'appreciations' => DB::table('appreciations')->where('collection_record_id', $record->id)->count(),
            'appreciated' => $request->user() && DB::table('appreciations')->where('collection_record_id', $record->id)->where('user_id', $request->user()->id)->exists(),
        ];
    }

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
    public static function publicArtwork(array $excludeCovers = [], int $limit = 6): array
    {
        return array_values(CollectionRecord::visibleTo(null)
            ->whereNotNull('payload->cover')->where('payload->cover', '!=', '')
            ->whereNotIn('payload->cover', $excludeCovers)
            ->inRandomOrder()->limit($limit)->get(['id', 'payload'])
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
            'avatarUrl' => $user->google_avatar_url,
            'discogsConnected' => filled($user->discogs_token),
            'discogsUsername' => $user->discogs_username,
            'importedAt' => $user->collection_imported_at?->toIso8601String(),
            'lastSyncedAt' => $user->last_synced_at?->toIso8601String(),
            'syncStatus' => $user->sync_status,
            'syncTotal' => $user->sync_total,
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
