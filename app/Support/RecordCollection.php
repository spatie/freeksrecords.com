<?php

namespace App\Support;

use App\Models\CollectionRecord;
use App\Models\Release;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class RecordCollection
{
    protected string $summariesCacheKey = 'record-collection-summaries-v2';

    public function __construct(
        protected RecordReleaseDates $dates,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function records(): array
    {
        if (! CollectionRecord::legacy()->exists() && User::freek()->collection_imported_at === null) {
            return $this->snapshot();
        }

        return CollectionRecord::legacy()
            ->orderByDesc('added_at')
            ->orderByDesc('instance_id')
            ->get()->map(fn (CollectionRecord $record): array => $record->payload)
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function find(int $instanceId): ?array
    {
        if (! CollectionRecord::legacy()->exists() && User::freek()->collection_imported_at === null) {
            return collect($this->snapshot())->firstWhere('instanceId', $instanceId);
        }

        return CollectionRecord::legacy()->where('instance_id', $instanceId)->first()?->payload;
    }

    /**
     * The records without their tracklists and the fields the shelf never
     * shows, enriched with release dates and share metadata. Cached until
     * the collection changes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function summaries(): array
    {
        return Cache::rememberForever(
            $this->summariesCacheKey,
            fn (): array => array_map($this->summary(...), $this->dates->enrich($this->records())),
        );
    }

    /** @return array<string, mixed>|null */
    public function findSummary(int $instanceId): ?array
    {
        return collect($this->summaries())->firstWhere('instanceId', $instanceId);
    }

    /** @return array<int, array<string, mixed>> */
    public function latest(int $count): array
    {
        return array_slice($this->summaries(), 0, $count);
    }

    /**
     * @param  list<CollectionRecord>  $copies
     * @return list<array<string, mixed>>
     */
    public function profileSummaries(array $copies): array
    {
        // Never use the legacy owner's cache or serialize an entire model/payload.
        $records = array_map(fn (CollectionRecord $copy): array => [
            ...Arr::only($copy->payload, [
                'artist', 'artists', 'title', 'displayTitle', 'year', 'originalYear',
                'label', 'format', 'genres', 'styles', 'cover', 'coverPreview', 'tracks',
                'appleMusicUrl', 'appleMusicSearch', 'spotifyUrl', 'spotifySearch',
            ]),
            'id' => $copy->discogs_release_id, 'instanceId' => $copy->instance_id,
            'addedAt' => $copy->added_at->toIso8601String(),
            'mediaFormats' => $copy->formats(),
            'cover' => filled($copy->payload['cover'] ?? null) && $copy->payload['cover'] !== '/images/record-placeholder.svg'
                ? route('shelf.cover', $copy, false)
                : ($copy->payload['cover'] ?? '/images/record-placeholder.svg'),
        ], $copies);

        return array_map(
            fn (array $record, CollectionRecord $copy): array => $this->summary($record, $copy->url(false)),
            $this->dates->enrich($records), $copies,
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function snapshot(): array
    {
        $records = File::json(resource_path('data/collection.json'))['records'] ?? null;

        if (! is_array($records)) {
            throw new RuntimeException('The bundled collection snapshot is unavailable.');
        }

        return $records;
    }

    /** @param array<string, mixed> $record */
    public function store(array $record): void
    {
        CollectionRecord::query()->updateOrCreate(
            ['user_id' => User::freek()->id, 'instance_id' => $record['instanceId']],
            [...$this->attributes($record), 'payload' => $record],
        );

        $this->flush();
    }

    /** @param array<int, array<string, mixed>> $records */
    public function storeMany(array $records): void
    {
        DB::transaction(function () use ($records): void {
            $knownIds = CollectionRecord::legacy()->whereIn('instance_id', array_column($records, 'instanceId'))->pluck('discogs_release_id')->all();
            $releases = [];
            foreach (collect($records)->unique('id') as $record) {
                $releases[$record['id']] = Release::storePayload($record['id'], $record, replace: in_array($record['id'], $knownIds, true))->recordData();
            }
            $now = now()->toDateTimeString();
            $rows = array_map(fn (array $record): array => [
                'instance_id' => $record['instanceId'],
                ...$this->attributes([...$record, ...$releases[$record['id']]]),
                'updated_at' => $now,
            ], $records);

            foreach (array_chunk($rows, 100) as $chunk) {
                CollectionRecord::query()->upsert(
                    $chunk,
                    ['user_id', 'instance_id'],
                    ['discogs_release_id', 'added_at', 'updated_at', 'browse_title', 'browse_original_year', 'browse_added_day', 'browse_search'],
                );
                CollectionBrowseIndex::sync(CollectionRecord::legacy()->whereIn('instance_id', array_column($chunk, 'instance_id'))
                    ->get(['id', 'user_id', 'discogs_release_id', 'instance_id', 'added_at']));
            }
        });

        $this->flush();
    }

    /** @param array<int, int> $instanceIds */
    public function removeAllExcept(array $instanceIds): int
    {
        $removed = CollectionRecord::legacy()->whereNotIn('instance_id', $instanceIds)->delete();

        $this->flush();

        return $removed;
    }

    /** @param array<string, mixed> $record */
    public function slug(array $record): string
    {
        return Str::slug("{$record['artist']} {$record['displayTitle']}");
    }

    public function flush(): void
    {
        Cache::forget($this->summariesCacheKey);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function attributes(array $record): array
    {
        return [
            'user_id' => User::freek()->id,
            'discogs_release_id' => $record['id'],
            'added_at' => CarbonImmutable::parse($record['addedAt'])->utc()->toDateTimeString(),
            ...CollectionBrowseIndex::attributes($record, CarbonImmutable::parse($record['addedAt'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function summary(array $record, ?string $profileUrl = null): array
    {
        $year = $record['originalYear'] ? " ({$record['originalYear']})" : '';

        $record['trackTitles'] = array_column($record['tracks'], 'title');
        $record['shareUrl'] = $profileUrl ?? route('recordShare', [
            'instanceId' => $record['instanceId'],
            'slug' => $this->slug($record),
        ], false);
        if ($profileUrl === null) {
            $record['shareDescription'] = "{$record['displayTitle']} by {$record['artist']}{$year}, from Freek's vinyl collection. Explore the artwork, tracklist and links to listen.";
        }

        return Arr::except($record, [
            'tracks',
            'artistBios',
            'releaseNotes',
            'credits',
            'catalogNumber',
            'coverSource',
            'discogsUrl',
            'edition',
            'musicbrainzId',
            'originalDateSource',
            'pressingReleaseDate',
            'trackSource',
        ]);
    }
}
