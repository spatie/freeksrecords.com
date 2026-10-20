<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FriendRequestResponseController extends Controller
{
    public function __invoke(Request $request, int $friendship, int $recipient, string $action): Response|RedirectResponse
    {
        abort_unless(in_array($action, ['accept', 'decline'], true), 404);
        $pending = Friendship::query()->whereKey($friendship)->whereNull('accepted_at')
            ->where('requested_by', '!=', $recipient)
            ->where(fn ($query) => $query->where('user_low', $recipient)->orWhere('user_high', $recipient))->first();

        if ($request->isMethod('post') && $pending) {
            $pending->respond(User::query()->findOrFail($recipient), $action);

            return redirect($request->fullUrl())->with('status', $action === 'accept' ? 'Friend request accepted.' : 'Friend request declined.');
        }

        return Inertia::render('social/FriendRequestResponsePage', [
            'sender' => $pending ? User::query()->findOrFail($pending->requested_by)->handle : null,
            'action' => $action,
            'responseUrl' => $request->fullUrl(),
        ]);
    }
}
