<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectionVisibilityController extends Controller
{
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasVerifiedEmail(), 403);

        $data = $request->validateWithBag('collectionVisibility', [
            'visibility' => ['required', 'string', Rule::in($user->legacy_owner ? ['everyone'] : ['only_me', 'friends', 'everyone'])],
        ], ['visibility.in' => $user->legacy_owner
            ? 'This original public collection must remain visible to everyone.'
            : 'Choose Only me, Friends, or Everyone.']);

        $user->update(['visibility' => $data['visibility']]);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['visibility' => $user->visibility]);
        }

        return redirect()->back(fallback: route('profile', $user->handle))->with('collection_visibility_saved', true);
    }
}
