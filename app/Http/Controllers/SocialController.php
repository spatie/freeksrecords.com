<?php

namespace App\Http\Controllers;

use App\Actions\AddRecordCommentAction;
use App\Actions\DeleteRecordCommentAction;
use App\Actions\UpdateFriendshipAction;
use App\Actions\UpdateRecordAppreciationAction;
use App\Models\CollectionRecord;
use App\Models\Comment;
use App\Models\Friendship;
use App\Models\SocialNotification;
use App\Models\User;
use App\Support\ProfileCollection;
use App\Support\RecordCollection;
use App\Support\SocialData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SocialController extends Controller
{
    public function profile(Request $request, User $user): InertiaResponse
    {
        $visible = $user->canViewCollection($request->user());
        $collection = new ProfileCollection($user, $request);

        return Inertia::render('social/ProfilePage', [
            'person' => SocialData::person($user), 'visible' => $visible,
            'friendsOnly' => $user->visibility === 'friends',
            'friendship' => ! $visible && $request->user() ? Friendship::query()->where([
                'user_low' => min($request->user()->id, $user->id),
                'user_high' => max($request->user()->id, $user->id),
            ])->first(['user_low', 'user_high', 'requested_by', 'accepted_at']) : null,
            'records' => $visible ? Inertia::scroll(fn () => $collection->records()) : null,
            'groups' => fn () => $visible ? $collection->groups() : [],
            'browse' => $collection->state(),
        ]);
    }

    public function record(Request $request, CollectionRecord $record): InertiaResponse
    {
        return Inertia::render('social/RecordPage', SocialData::record($request, $record));
    }

    public function profileRecord(Request $request, User $user, CollectionRecord $record): InertiaResponse
    {
        abort_unless($record->user_id === $user->id, 404);

        return match ($request->header('X-Inertia-Partial-Component')) {
            'social/HomePage' => app(HomeController::class)->intro(),
            'social/CollectionsPage' => app(HomeController::class)->collections(),
            'social/FeedPage' => $request->user()?->hasVerifiedEmail()
                ? $this->feed($request)
                : $this->profile($request, $user),
            default => $this->profile($request, $user),
        };
    }

    public function feed(Request $request): InertiaResponse
    {
        $user = $request->user();
        $records = CollectionRecord::friendActivity($user, includeLatestRecords: true)->with('user')->paginate(24);
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
                ? app(RecordCollection::class)->profileSummaries(array_values($user->records()->orderByDesc('added_at')->orderByDesc('id')->limit(48)->get()->all()))
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
        })->withCount('records')->orderBy('handle')->paginate(30)->withQueryString();

        return Inertia::render('social/FriendsPage', [
            'query' => $query,
            'people' => SocialData::pagination($people, $people->getCollection()->map(fn (User $person) => [
                ...SocialData::person($person),
                'recordCount' => $person->canViewCollection($user) ? $person->records_count : null,
            ])->all()),
            'connections' => $connections->map(fn (Friendship $friendship) => [
                'user_low' => $friendship->user_low,
                'user_high' => $friendship->user_high,
                'requested_by' => $friendship->requested_by,
                'accepted_at' => $friendship->accepted_at,
            ])->all(),
        ]);
    }

    public function friend(Request $request, User $user, UpdateFriendshipAction $updateFriendship): RedirectResponse
    {
        $viewer = $request->user();
        abort_if($viewer->id === $user->id, 422);
        $data = $request->validate(['action' => ['required', 'in:request,accept,decline,cancel,unfriend']]);
        $updateFriendship->execute($viewer, $user, $data['action']);

        return back();
    }

    public function appreciate(Request $request, CollectionRecord $record, UpdateRecordAppreciationAction $updateAppreciation): RedirectResponse
    {
        $updateAppreciation->execute($request->user(), $record, true);

        return back();
    }

    public function unappreciate(Request $request, CollectionRecord $record, UpdateRecordAppreciationAction $updateAppreciation): RedirectResponse
    {
        $updateAppreciation->execute($request->user(), $record, false);

        return back();
    }

    public function comment(Request $request, CollectionRecord $record, AddRecordCommentAction $addComment): RedirectResponse
    {
        $addComment->execute($request->user(), $record, $request->input('body'));

        return back()->with('status', 'Comment added.');
    }

    public function deleteComment(Request $request, CollectionRecord $record, Comment $comment, DeleteRecordCommentAction $deleteComment): RedirectResponse
    {
        $deleteComment->execute($request->user(), $record, $comment);

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
                    ? $notification->record->url(false).($notification->kind === 'comment' ? '#comments' : '')
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
