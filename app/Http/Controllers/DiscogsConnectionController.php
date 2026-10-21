<?php

namespace App\Http\Controllers;

use App\Actions\ConnectDiscogsAccountAction;
use App\Actions\DisconnectDiscogsAccountAction;
use App\Jobs\SyncUserCollection;
use App\Support\DiscogsOAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

class DiscogsConnectionController extends Controller
{
    public function connect(Request $request, DiscogsOAuth $oauth): RedirectResponse
    {
        if (! config('services.discogs.consumer_key') || ! config('services.discogs.consumer_secret')) {
            return back()->withErrors(['discogs' => 'Discogs connection is not configured yet. Ask the site owner to configure the OAuth app.']);
        }
        try {
            $response = $oauth->request('GET', 'oauth/request_token', extra: ['oauth_callback' => route('discogs.callback')]);
            parse_str($response->body(), $tokens);
            if (! is_string($tokens['oauth_token'] ?? null) || $tokens['oauth_token'] === ''
                || ! is_string($tokens['oauth_token_secret'] ?? null) || $tokens['oauth_token_secret'] === ''
                || ($tokens['oauth_callback_confirmed'] ?? '') !== 'true') {
                throw new RuntimeException('Invalid token response');
            }
            $request->session()->put('discogs_pending', [
                'token' => $tokens['oauth_token'], 'secret' => Crypt::encryptString($tokens['oauth_token_secret']),
                'user_id' => $request->user()->id, 'expires' => now()->addMinutes(15)->timestamp,
            ]);
        } catch (Throwable) {
            return back()->withErrors(['discogs' => 'Discogs could not start the connection. Please try again.']);
        }

        return redirect()->away('https://www.discogs.com/oauth/authorize?oauth_token='.rawurlencode($tokens['oauth_token']));
    }

    public function callback(Request $request, DiscogsOAuth $oauth, ConnectDiscogsAccountAction $connectAccount): RedirectResponse
    {
        $pending = $request->session()->pull('discogs_pending');
        $token = $request->query('oauth_token');
        $verifier = $request->query('oauth_verifier');
        abort_unless(is_array($pending) && $pending['user_id'] === $request->user()->id
            && $pending['expires'] >= now()->timestamp
            && is_string($token) && hash_equals($pending['token'], $token)
            && is_string($verifier) && $verifier !== '', 403);
        try {
            $response = $oauth->request('POST', 'oauth/access_token', $pending['token'], Crypt::decryptString($pending['secret']), ['oauth_verifier' => $verifier]);
            parse_str($response->body(), $tokens);
            if (! is_string($tokens['oauth_token'] ?? null) || $tokens['oauth_token'] === ''
                || ! is_string($tokens['oauth_token_secret'] ?? null) || $tokens['oauth_token_secret'] === '') {
                throw new RuntimeException('Invalid token response');
            }
            $identity = $oauth->request('GET', 'oauth/identity', $tokens['oauth_token'], $tokens['oauth_token_secret'])->json();
            if (! is_string($identity['username'] ?? null) || ! preg_match('/^[a-zA-Z0-9_.-]+$/', $identity['username'])) {
                throw new RuntimeException('Invalid identity response');
            }
            $connectAccount->execute($request->user(), $identity['username'], $tokens['oauth_token'], $tokens['oauth_token_secret']);
        } catch (Throwable) {
            return redirect('/settings')->withErrors(['discogs' => 'Discogs connection failed. Retry with your original Discogs account; an account can belong to only one profile.']);
        }
        SyncUserCollection::dispatch($request->user()->id);

        return redirect('/feed')->with('status', 'Discogs connected.');
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'collectorId' => $user->id,
            'collectionUrl' => route('profile', $user->handle),
            'visibility' => match ($user->visibility) {
                'everyone' => 'Everyone', 'friends' => 'Friends', default => 'Only me',
            },
            'connected' => $user->discogs_token !== null,
            'complete' => $user->collection_imported_at !== null,
            'count' => $user->records()->count(),
            'total' => $user->sync_total,
            'status' => $user->sync_status ?? 'Waiting for the import to start',
            'failed' => str_starts_with($user->sync_status ?? '', 'Sync failed'),
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        abort_unless($request->user()->discogs_token !== null, 422);
        SyncUserCollection::dispatch($request->user()->id);

        return back()->with('status', 'Collection sync requested.');
    }

    public function disconnect(Request $request, DisconnectDiscogsAccountAction $disconnectAccount): RedirectResponse
    {
        $disconnectAccount->execute($request->user());
        $request->session()->forget('discogs_pending');

        return back()->with('status', 'Discogs disconnected. Imported records are kept; reconnect the same account to resume. Revoke the app on Discogs to revoke its grant too.');
    }
}
