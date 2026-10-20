<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RecordCollection;
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
        $previewCovers = $collectors->flatMap(fn (User $collector) => $collector->records->take(4)->pluck('payload.cover'));
        $heroCovers = collect(app(RecordCollection::class)->snapshot())
            ->pluck('cover')
            ->filter(fn (string $cover) => str_starts_with($cover, '/images/records/') && is_file(public_path($cover)))
            ->unique()
            ->reject(fn (string $cover) => $previewCovers->contains($cover))
            ->shuffle()
            ->take(24)
            ->values();

        return Inertia::render('social/HomePage', [
            'collectors' => $collectors->map(SocialData::collector(...))->all(),
            'heroCovers' => $heroCovers->all(),
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
            ->with(['records' => fn ($query) => $query->select(['id', 'user_id', 'payload'])->orderBy('id')->limit(6)])
            ->orderBy('handle');
    }
}
