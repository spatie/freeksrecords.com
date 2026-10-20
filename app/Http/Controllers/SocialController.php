<?php

namespace App\Http\Controllers;

use App\Models\CollectionRecord;
use App\Models\Comment;
use App\Models\Friendship;
use App\Models\SocialNotification;
use App\Models\User;
use App\Notifications\FriendRequest;
use App\Support\RecordCollection;
use App\Support\SocialData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SocialController extends Controller
{
    public function profile(Request $request, User $user): InertiaResponse
    {
        $visible = $user->canViewCollection($request->user());

        return Inertia::render('social/ProfilePage', [
            'person' => SocialData::person($user), 'visible' => $visible,
            'records' => $visible ? app(RecordCollection::class)->profileSummaries(
                array_values($user->records()->orderByDesc('added_at')->orderByDesc('id')
                    ->get(['id', 'instance_id', 'discogs_release_id', 'added_at', 'payload'])->all()),
            ) : null,
        ]);
    }

    public function record(Request $request, CollectionRecord $record): InertiaResponse
    {
        return Inertia::render('social/RecordPage', SocialData::record($request, $record));
    }

    public function feed(Request $request): InertiaResponse
    {
        $user = $request->user();
        $records = CollectionRecord::friendActivity($user)->with('user')->paginate(24);
        $copies = array_values($records->items());
        $feedRecords = array_map(
            fn (array $summary, CollectionRecord $record): array => [
                ...$summary,
                'owner' => [
                    'name' => $record->user->name,
                    'profileUrl' => route('profile', $record->user->handle, false),
                ],
            ],
            app(RecordCollection::class)->profileSummaries($copies),
            $copies,
        );

        return Inertia::render('social/FeedPage', [
            'records' => SocialData::pagination($records, $feedRecords),
            'importRecords' => fn () => $user->discogs_token && ! $user->collection_imported_at
                ? app(RecordCollection::class)->profileSummaries(array_values($user->records()->orderByDesc('added_at')->orderByDesc('id')->get()->all()))
                : [],
            'collectionCount' => $user->records()->count(),
        ]);
    }

    public function friends(Request $request): InertiaResponse
    {
        $user = $request->user();
        $request->validate(['q' => ['nullable', 'string', 'max:31']]);
        $query = $request->string('q')->trim()->lower()->ltrim('@')->toString();
        $connections = Friendship::query()->where('user_low', $user->id)->orWhere('user_high', $user->id)->get();
        $people = User::query()->whereKeyNot($user->id)->where(function ($builder) use ($query, $connections, $user): void {
            if ($query !== '') {
                $builder->where('handle', $query);
            } else {
                $builder->whereIn('id', $connections->map(fn (Friendship $friendship) => $friendship->user_low === $user->id ? $friendship->user_high : $friendship->user_low));
            }
        })->orderBy('handle')->paginate(30)->withQueryString();

        return Inertia::render('social/FriendsPage', [
            'query' => $query,
            'people' => SocialData::pagination($people, $people->getCollection()->map(SocialData::person(...))->all()),
            'connections' => $connections->map(fn (Friendship $friendship) => [
                'user_low' => $friendship->user_low,
                'user_high' => $friendship->user_high,
                'requested_by' => $friendship->requested_by,
                'accepted_at' => $friendship->accepted_at,
            ])->all(),
        ]);
    }

    public function friend(Request $request, User $user): RedirectResponse
    {
        $viewer = $request->user();
        abort_if($viewer->id === $user->id, 422);
        $data = $request->validate(['action' => 'required|in:request,accept,decline,cancel,unfriend']);
        DB::transaction(function () use ($user, $viewer, $data): void {
            // Serializes both directions, including two simultaneous first requests.
            User::query()->whereIn('id', [$viewer->id, $user->id])->orderBy('id')->lockForUpdate()->get();
            $pair = ['user_low' => min($viewer->id, $user->id), 'user_high' => max($viewer->id, $user->id)];
            $friendship = Friendship::query()->where($pair)->first();
            if ($data['action'] === 'request') {
                if ($friendship === null) {
                    $friendship = Friendship::query()->create([...$pair, 'requested_by' => $viewer->id]);
                    SocialNotification::send($user, $viewer, 'friend_request');
                    $user->notify(new FriendRequest($friendship->id));
                }
            } elseif ($data['action'] === 'accept') {
                abort_unless($friendship && ! $friendship->accepted_at && $friendship->requested_by !== $viewer->id, 403);
                $friendship->respond($viewer, 'accept');
            } else {
                abort_unless($friendship !== null, 404);
                abort_unless(match ($data['action']) {
                    'unfriend' => $friendship->accepted_at !== null,
                    'cancel' => $friendship->accepted_at === null && $friendship->requested_by === $viewer->id,
                    'decline' => $friendship->accepted_at === null && $friendship->requested_by !== $viewer->id,
                    default => false,
                }, 403);
                if ($data['action'] === 'decline') {
                    $friendship->respond($viewer, 'decline');
                } else {
                    $friendship->delete();
                }
            }
        });

        return back()->with('status', 'Friendship updated.');
    }

    public function appreciate(Request $request, CollectionRecord $record): RedirectResponse
    {
        abort_unless($record->user->canViewCollection($request->user()), 404);
        $created = DB::table('appreciations')->insertOrIgnore(['user_id' => $request->user()->id, 'collection_record_id' => $record->id]);
        if ($created) {
            SocialNotification::send($record->user, $request->user(), 'appreciation', $record);
        }

        return back();
    }

    public function unappreciate(Request $request, CollectionRecord $record): RedirectResponse
    {
        abort_unless($record->user->canViewCollection($request->user()), 404);
        DB::table('appreciations')->where('user_id', $request->user()->id)->where('collection_record_id', $record->id)->delete();

        return back();
    }

    public function comment(Request $request, CollectionRecord $record): RedirectResponse
    {
        abort_unless($record->user->canViewCollection($request->user()), 404);
        abort_unless($record->user->comments_enabled, 403);
        $data = $request->validate(['body' => 'required|string|max:2000']);
        $record->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        SocialNotification::send($record->user, $request->user(), 'comment', $record);

        return back()->with('status', 'Comment added.');
    }

    public function deleteComment(Request $request, CollectionRecord $record, Comment $comment): RedirectResponse
    {
        abort_unless($comment->collection_record_id === $record->id, 404);
        // Authors retain their deletion right even if they lose collection access.
        abort_if($request->user()->id !== $comment->user_id && $request->user()->id !== $record->user_id, 403);
        $comment->delete();

        return back()->with('status', 'Comment deleted.');
    }

    public function notifications(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->ajax()) {
            return redirect()->route('feed', ['notifications' => 1]);
        }

        $notifications = SocialNotification::visibleTo($request->user())->with(['actor', 'record'])->latest('id')->simplePaginate(15);
        $pendingSenders = Friendship::query()->whereNull('accepted_at')->where('requested_by', '!=', $request->user()->id)
            ->where(fn ($query) => $query->where('user_low', $request->user()->id)->orWhere('user_high', $request->user()->id))
            ->pluck('requested_by');

        return response()->json([
            'notifications' => SocialData::pagination($notifications, $notifications->getCollection()->map(fn (SocialNotification $notification) => [
                'id' => $notification->id,
                'actor' => SocialData::person($notification->actor),
                'text' => match ($notification->kind) {
                    'friend_request' => 'sent you a friend request.',
                    'friend_accepted' => 'accepted your friend request.',
                    'appreciation' => 'appreciated your record.',
                    'comment' => 'commented on your record.',
                    default => 'updated your listening circle.',
                },
                'record' => $notification->record ? [
                    'title' => $notification->record->payload['displayTitle'],
                    'artist' => $notification->record->payload['artist'],
                    'cover' => $notification->record->payload['cover'],
                ] : null,
                'url' => $notification->record
                    ? route('shelf.record', $notification->record, false).($notification->kind === 'comment' ? '#comments' : '')
                    : route('friends', absolute: false),
                'unread' => $notification->read_at === null,
                'canRespond' => $notification->kind === 'friend_request' && $pendingSenders->contains($notification->actor_id),
            ])->all()),
            'unreadCount' => SocialNotification::visibleTo($request->user())->whereNull('read_at')->count(),
        ]);
    }

    public function readNotifications(Request $request): RedirectResponse|Response
    {
        SocialNotification::visibleTo($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->noContent();
        }

        return back()->with('status', 'Notifications marked as read.');
    }
}
