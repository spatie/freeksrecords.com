<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($user = $request->user()) {
            return redirect()->route($user->hasVerifiedEmail() ? 'feed' : 'verification.notice');
        }

        return $this->intro();
    }

    public function intro(): View
    {
        return view('social.intro', ['collectors' => $this->publicCollectors()->limit(3)->get()]);
    }

    public function collections(): View
    {
        return view('social.collections', ['collectors' => $this->publicCollectors()->paginate(12)]);
    }

    /** @return Builder<User> */
    protected function publicCollectors(): Builder
    {
        return User::query()->where('visibility', 'everyone')->has('records')
            ->withCount('records')
            ->with(['records' => fn ($query) => $query->select(['id', 'user_id', 'payload'])->orderBy('id')->limit(6)])
            ->orderBy('handle');
    }
}
