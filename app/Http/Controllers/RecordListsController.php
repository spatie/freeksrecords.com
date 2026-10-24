<?php

namespace App\Http\Controllers;

use App\Actions\Lists\CreateRecordListAction;
use App\Actions\Lists\DeleteRecordListAction;
use App\Actions\Lists\UpdateRecordListAction;
use App\Models\CollectionRecord;
use App\Models\RecordList;
use App\Support\ProfileCollection;
use App\Support\RecordCollection;
use App\Support\SocialData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecordListsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('social/ListsPage', [
            'lists' => RecordList::previews($request->user())->where('user_id', $request->user()->id)
                ->get()->map(SocialData::listPreview(...)),
        ]);
    }

    public function store(Request $request, CreateRecordListAction $createList): RedirectResponse
    {
        $list = $createList->execute($request->user(), $request->all());

        if ($request->has('copyId')) {
            $copy = $list->records()->firstOrFail();

            return $request->header('X-Inertia-Partial-Component') === 'social/RecordPage'
                ? to_route('shelf.record', $copy)
                : redirect($copy->url(false));
        }

        return to_route('lists.edit', $list);
    }

    public function show(Request $request, RecordList $recordList): Response|RedirectResponse
    {
        if (! $request->user() && $recordList->visibility === 'friends') {
            return redirect()->guest(route('login'));
        }

        abort_unless($recordList->canView($request->user()), 404);

        return Inertia::render('social/ListPage', $this->data($request, $recordList));
    }

    public function edit(Request $request, RecordList $recordList): Response
    {
        abort_unless($recordList->user_id === $request->user()->id, 404);
        $collection = new ProfileCollection($request->user(), $request);
        $page = $collection->records();
        $copies = $request->user()->records()->whereIn('public_id', array_column($page->items(), 'publicId'))
            ->pluck('id', 'public_id');

        return Inertia::render('social/ListEditPage', [
            ...$this->data($request, $recordList),
            'query' => $collection->state()['query'],
            'results' => SocialData::pagination($page, array_map(fn (array $record) => [
                'copyId' => $copies[$record['publicId']],
                'copyPublicId' => $record['publicId'],
                ...Arr::only($record, ['artist', 'displayTitle', 'year', 'originalYear', 'format', 'cover']),
            ], $page->items())),
        ]);
    }

    public function update(Request $request, RecordList $recordList, UpdateRecordListAction $updateList): RedirectResponse
    {
        $updateList->execute($request->user(), $recordList, $request->all());

        if ($request->has('recordIds')) {
            return to_route('lists.index')->with('status', 'List saved.');
        }

        return to_route('lists.show', $recordList);
    }

    public function destroy(Request $request, RecordList $recordList, DeleteRecordListAction $deleteList): RedirectResponse
    {
        $deleteList->execute($request->user(), $recordList);

        return to_route('lists.index')->with('status', 'List deleted. Your records are still in your collection.');
    }

    /** @return array<string, mixed> */
    protected function data(Request $request, RecordList $list): array
    {
        return [
            'list' => fn () => [
                ...$list->only(['id', 'name', 'description', 'visibility']),
                'publicId' => $list->public_id,
                'url' => route('lists.show', $list),
                'owner' => SocialData::person($list->user),
                'canEdit' => $request->user()?->id === $list->user_id && $request->user()->hasVerifiedEmail(),
                'removedCount' => $request->user()?->id === $list->user_id
                    ? DB::table('record_list_entries')->where('record_list_id', $list->id)->whereNull('collection_record_id')->count() : 0,
            ],
            'records' => function () use ($list): array {
                $copies = array_values($list->records()->with('user')->get()->all());
                $records = app(RecordCollection::class)->profileSummaries($copies);

                return array_map(fn (array $record, CollectionRecord $copy) => [
                    ...Arr::except($record, ['shareUrl']),
                    'copyId' => $copy->id,
                    'copyPublicId' => $copy->public_id,
                    'cover' => filled($copy->payload['cover'] ?? null) && $copy->payload['cover'] !== '/images/record-placeholder.svg'
                        ? route('lists.cover', [$list, $copy], false) : '/images/record-placeholder.svg',
                    'shareUrl' => route('lists.show', $list, false).'#record-'.$copy->id,
                ], $records, $copies);
            },
        ];
    }
}
