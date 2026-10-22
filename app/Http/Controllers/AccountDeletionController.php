<?php

namespace App\Http\Controllers;

use App\Actions\DeleteAccountAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountDeletionController
{
    public function show(): Response
    {
        return Inertia::render('social/DeleteAccountPage');
    }

    public function destroy(Request $request, DeleteAccountAction $deleteAccount): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', 'string', Rule::in([$request->user()->handle])]], [
            'confirmation.in' => 'Type your username exactly to confirm account deletion.',
        ]);

        $deleteAccount->execute($request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();

        return redirect('/')->with('status', 'Your Plaat Club account has been deleted. Your Discogs collection is untouched.');
    }
}
