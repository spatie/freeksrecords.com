<?php

namespace App\Http\Controllers;

use App\Models\CollectionRecord;
use App\Models\Comment;
use App\Models\Friendship;
use App\Models\SocialNotification;
use App\Models\User;
use App\Support\RecordCollection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SocialController extends Controller
{
    public function profile(Request $request, User $user): View
    {
        $visible = $user->canViewCollection($request->user());

        return view('social.profile', [
            'person' => $user, 'visible' => $visible,
            'records' => $visible ? app(RecordCollection::class)->profileSummaries(
                array_values($user->records()->orderByDesc('added_at')->orderByDesc('id')
                    ->get(['id', 'instance_id', 'discogs_release_id', 'added_at', 'payload'])->all()),
            ) : null,
        ]);
    }

    public function record(Request $request, CollectionRecord $record): View
    {
        abort_unless($record->user->canViewCollection($request->user()), 404);

        return view('social.record', [
            'record' => $record, 'comments' => $record->comments()->with('user')->oldest('id')->paginate(30),
            'appreciations' => DB::table('appreciations')->where('collection_record_id', $record->id)->count(),
            'appreciated' => $request->user() && DB::table('appreciations')->where('collection_record_id', $record->id)->where('user_id', $request->user()->id)->exists(),
        ]);
    }

    public function feed(Request $request): View
    {
        $user = $request->user();

        return view('social.feed', [
            'records' => CollectionRecord::friendActivity($user)->with('user')->paginate(24),
            'collectionCount' => $user->records()->count(),
            'incomingRequests' => User::query()->whereIn('id', Friendship::query()
                ->whereNull('accepted_at')->where('requested_by', '!=', $user->id)
                ->where(fn ($query) => $query->where('user_low', $user->id)->orWhere('user_high', $user->id))
                ->select('requested_by'))->orderBy('name')->get(),
        ]);
    }

    public function friends(Request $request): View
    {
        $user = $request->user();
        $request->validate(['q' => 'nullable|string|max:30']);
        $query = strtolower($request->string('q')->trim());
        $connections = Friendship::query()->where('user_low', $user->id)->orWhere('user_high', $user->id)->get();
        $people = User::query()->whereKeyNot($user->id)->where(function ($builder) use ($query, $connections, $user): void {
            if ($query !== '') {
                $builder->where('handle', $query);
            } else {
                $builder->whereIn('id', $connections->map(fn (Friendship $friendship) => $friendship->user_low === $user->id ? $friendship->user_high : $friendship->user_low));
            }
        })->orderBy('handle')->paginate(30)->withQueryString();

        return view('social.friends', compact('people', 'connections', 'query'));
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
                    Friendship::query()->create([...$pair, 'requested_by' => $viewer->id]);
                    SocialNotification::send($user, $viewer, 'friend_request');
                }
            } elseif ($data['action'] === 'accept') {
                abort_unless($friendship && ! $friendship->accepted_at && $friendship->requested_by !== $viewer->id, 403);
                $friendship->update(['accepted_at' => now()]);
                SocialNotification::send($user, $viewer, 'friend_accepted');
            } else {
                abort_unless($friendship !== null, 404);
                abort_unless(match ($data['action']) {
                    'unfriend' => $friendship->accepted_at !== null,
                    'cancel' => $friendship->accepted_at === null && $friendship->requested_by === $viewer->id,
                    'decline' => $friendship->accepted_at === null && $friendship->requested_by !== $viewer->id,
                    default => false,
                }, 403);
                $friendship->delete();
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

    public function notifications(Request $request): View|RedirectResponse
    {
        if (! $request->ajax()) {
            return redirect()->route('feed', ['notifications' => 1]);
        }

        return view('social.notifications', [
            'notifications' => SocialNotification::visibleTo($request->user())->with(['actor', 'record'])->latest('id')->simplePaginate(15),
            'unreadCount' => SocialNotification::visibleTo($request->user())->whereNull('read_at')->count(),
        ]);
    }

    public function readNotifications(Request $request): RedirectResponse|Response
    {
        SocialNotification::visibleTo($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        if ($request->ajax()) {
            return response()->noContent();
        }

        return back()->with('status', 'Notifications marked as read.');
    }

    public function previewDigest(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local') && config('services.social_demo'), 404);
        Artisan::call('social:send-digests', ['--preview-for' => $request->user()->id]);

        return back()->with('status', 'Digest preview checked for the last seven days. If enabled and there are visible additions, an email is in the preview inbox. Empty weeks send nothing.');
    }
}
