<?php

namespace App\Http\Controllers;

use App\Mail\FriendsWeeklyDigest;
use App\Models\CollectionRecord;
use App\Models\User;
use App\Support\SocialData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($user = $request->user()) {
            if (! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            $record = $request->query('record');
            if (is_string($record) && Str::isUuid($record)) {
                return redirect()->route('shelf.record', $record);
            }

            return redirect()->route('profile', $user->handle);
        }

        return $this->intro();
    }

    public function intro(): Response
    {
        return Inertia::render('social/HomePage', [
            'artwork' => SocialData::publicArtwork(),
        ]);
    }

    public function collections(): Response
    {
        $collectors = $this->publicCollectors()->paginate(12);

        return Inertia::render('social/CollectionsPage', [
            'collectors' => SocialData::pagination($collectors, $collectors->getCollection()->map(SocialData::collector(...))->all()),
        ]);
    }

    public function weeklyEmail(): Response
    {
        $records = new Collection;
        foreach ([
            ['Alex', 'Midnight Windows', 'Northbound', 'Vinyl'],
            ['Robin', 'Sunday Static', 'Soft Circuit', 'CD'],
            ['Alex', 'After the Last Tram', 'Paper Satellites', 'Cassette'],
        ] as $index => [$name, $title, $artist, $format]) {
            $record = new CollectionRecord([
                'id' => $index + 1,
                'public_id' => (string) Str::uuid(),
                'added_at' => now()->subDays($index + 1),
                'payload' => ['displayTitle' => $title, 'artist' => $artist, 'mediaFormats' => [$format], 'cover' => '/images/demo-sleeve-'.($index + 1).'.jpg'],
            ]);
            $record->setRelation('user', new User(['name' => $name, 'handle' => 'email_example']));
            $records->push($record);
        }

        return Inertia::render('social/WeeklyEmailPage', [
            'emailHtml' => (new FriendsWeeklyDigest($records))->with('webPreview', true)->render(),
        ]);
    }

    /** @return Builder<User> */
    protected function publicCollectors(): Builder
    {
        return User::query()->where('visibility', 'everyone')->has('records')
            ->withCount('records')
            ->with(['records' => fn ($query) => $query->select(['id', 'public_id', 'user_id', 'discogs_release_id', 'instance_id', 'added_at'])->orderByDesc('added_at')->orderByDesc('id')->limit(6)])
            ->orderBy('handle');
    }
}
