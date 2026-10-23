<?php

namespace App\Http\Controllers;

use App\Actions\Social\UpdateFriendshipAction;
use App\Models\Friendship;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationMail;
use App\Support\SocialData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationsController
{
    public function create(): Response
    {
        return Inertia::render('social/InviteFriendsPage');
    }

    public function store(Request $request, UpdateFriendshipAction $friendship): RedirectResponse
    {
        $request->merge(['email' => is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : $request->input('email')]);
        $email = $request->validate(['email' => ['required', 'string', 'email', 'max:255']])['email'];
        $sender = $request->user();
        if ($email === mb_strtolower(trim($sender->email))) {
            throw ValidationException::withMessages(['email' => 'Invite a friend, not yourself.']);
        }

        DB::transaction(function () use ($sender, $email, $friendship): void {
            User::query()->whereKey($sender->id)->lockForUpdate()->firstOrFail();
            $invitation = Invitation::query()->where('inviter_id', $sender->id)->where('email', $email)->first();
            if ($invitation && $invitation->last_sent_at->isAfter(now()->subDay())) {
                return;
            }

            $key = "invitations:{$sender->id}";
            if (RateLimiter::tooManyAttempts($key, 20)) {
                throw ValidationException::withMessages(['email' => 'You’ve reached your daily invitation limit. Try again tomorrow.']);
            }
            RateLimiter::hit($key, 86400);

            $recipient = User::query()->whereRaw('LOWER(email) = ?', [$email])->whereNotNull('email_verified_at')->first();
            $token = Str::random(64);
            $invitation = Invitation::query()->updateOrCreate(['inviter_id' => $sender->id, 'email' => $email], [
                'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7),
                'last_sent_at' => now(), 'fulfilled_at' => $recipient ? now() : null,
            ]);
            if ($recipient) {
                $friendship->execute($sender, $recipient, 'request');

                return;
            }

            Notification::route('mail', $email)->notify(new InvitationMail($invitation->id, $token));
            activity('social')->causedBy($sender)->performedOn($invitation)
                ->event('invitation_sent')->log('Invitation sent');
        }, 3);

        return back()->with('status', 'Invitation sent. If your friend is already on Plaat Club, they’ll find your friend request there.');
    }

    public function show(Request $request, string $token): Response
    {
        abort_unless(strlen($token) === 64, 404);
        $invitation = Invitation::query()->with('inviter')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->firstOrFail();
        $viewer = $request->user();
        if (! $viewer || ! $viewer->hasVerifiedEmail()) {
            $request->session()->put('url.intended', route('invitations.show', $token));
        }
        $matches = $viewer && mb_strtolower(trim($viewer->email)) === $invitation->email;
        $connection = $matches && $viewer->hasVerifiedEmail()
            ? Friendship::query()->where('user_low', min($viewer->id, $invitation->inviter_id))->where('user_high', max($viewer->id, $invitation->inviter_id))->first()
            : null;

        return Inertia::render('social/InvitationPage', [
            'sender' => SocialData::person($invitation->inviter),
            'matches' => (bool) $matches,
            'friendship' => $connection ? $connection->only(['requested_by', 'accepted_at']) : null,
            'fulfilled' => $invitation->fulfilled_at !== null,
        ]);
    }
}
