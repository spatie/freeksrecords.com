<?php

namespace App\Http\Controllers;

use App\Actions\Lists\UpdateRecordListMembershipAction;
use App\Models\CollectionRecord;
use App\Models\RecordList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class RecordListRecordsController extends Controller
{
    public function show(Request $request, RecordList $recordList, CollectionRecord $copy): JsonResponse
    {
        abort_unless($recordList->canView($request->user()), 404);
        abort_unless($recordList->records()->whereKey($copy->id)->exists(), 404);

        $payload = Arr::only($copy->payload, [
            'id', 'instanceId', 'artist', 'artists', 'title', 'displayTitle', 'edition',
            'year', 'originalYear', 'originalReleaseDate', 'pressingReleaseDate', 'addedAt',
            'label', 'catalogNumber', 'format', 'genres', 'styles', 'discogsUrl',
            'appleMusicUrl', 'appleMusicSearch', 'spotifyUrl', 'spotifySearch', 'trackSource',
        ]);
        $payload['cover'] = route('lists.cover', [$recordList, $copy], false);
        $payload['tracks'] = array_map(fn (array $track) => Arr::only($track, [
            'position', 'title', 'artist', 'duration', 'appleMusicUrl', 'appleMusicSearch', 'spotifyUrl', 'spotifySearch',
        ]), $copy->payload['tracks'] ?? []);

        return response()->json($payload);
    }

    public function store(Request $request, RecordList $recordList, CollectionRecord $copy, UpdateRecordListMembershipAction $updateMembership): RedirectResponse
    {
        $recordUrl = $request->header('X-Inertia-Partial-Component') === 'social/RecordPage'
            ? route('shelf.record', $copy)
            : $copy->url(false);

        try {
            $updateMembership->execute($request->user(), $recordList, $copy, true);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo($recordUrl);
        }

        return redirect($recordUrl);
    }

    public function destroy(Request $request, RecordList $recordList, CollectionRecord $copy, UpdateRecordListMembershipAction $updateMembership): RedirectResponse
    {
        $updateMembership->execute($request->user(), $recordList, $copy, false);

        return $request->header('X-Inertia-Partial-Component') === 'social/RecordPage'
            ? to_route('shelf.record', $copy)
            : redirect($copy->url(false));
    }
}
