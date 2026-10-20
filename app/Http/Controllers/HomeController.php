<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SocialData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($user = $request->user()) {
            return redirect()->route($user->hasVerifiedEmail() ? 'feed' : 'verification.notice');
        }

        return $this->intro();
    }

    public function intro(): Response
    {
        $collectors = $this->publicCollectors()->limit(3)->get();
        $previewCovers = array_values(array_filter(
            $collectors->flatMap(fn (User $collector) => $collector->records->take(4)->pluck('payload.cover'))->all(),
            'is_string',
        ));

        return Inertia::render('social/HomePage', [
            'collectors' => $collectors->map(SocialData::collector(...))->all(),
            'artwork' => SocialData::publicArtwork($previewCovers),
        ]);
    }

    public function collections(): Response
    {
        $collectors = $this->publicCollectors()->paginate(12);

        return Inertia::render('social/CollectionsPage', [
            'collectors' => SocialData::pagination($collectors, $collectors->getCollection()->map(SocialData::collector(...))->all()),
        ]);
    }

    /** @return Builder<User> */
    protected function publicCollectors(): Builder
    {
        return User::query()->where('visibility', 'everyone')->has('records')
            ->withCount('records')
            ->with(['records' => fn ($query) => $query->select(['id', 'user_id', 'payload'])->orderByDesc('added_at')->orderByDesc('id')->limit(6)])
            ->orderBy('handle');
    }
}
