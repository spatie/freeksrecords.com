<?php

namespace App\Console\Commands;

use App\Models\RecordReleaseDate;
use App\Support\RecordCollection;
use App\Support\RecordReleaseDates;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class SyncRecordDatesCommand extends Command
{
    protected $signature = 'records:sync-dates {--musicbrainz : Look up missing original dates}';

    protected $description = 'Store original and pressing dates with their known precision and source';

    public function handle(RecordReleaseDates $dates, RecordCollection $collection): int
    {
        $records = collect($collection->records())->unique('id');
        $stored = RecordReleaseDate::query()->get()->keyBy('discogs_release_id');
        $now = now()->toDateTimeString();
        $matched = 0;
        $rows = [];

        foreach ($records as $record) {
            $this->line("Storing dates of {$record['artist']} / {$record['displayTitle']}...");

            $existing = $stored->get($record['id']);
            $discogsYear = $record['originalYear'] ? (string) $record['originalYear'] : null;

            $original = $dates->normalize($record['originalReleaseDate'] ?? null) ?? $discogsYear;
            $source = $record['originalDateSource'] ?? ($original ? 'discogs' : null);
            $musicbrainzId = $record['musicbrainzId'] ?? null;

            if ($existing?->original_release_date) {
                if (! $original || str_starts_with($existing->original_release_date, $original)) {
                    $original = $existing->original_release_date;
                    $source = $existing->original_date_source;
                    $musicbrainzId = $existing->musicbrainz_id;
                }
            }

            if ($this->option('musicbrainz')) {
                if (! $original) {
                    $match = $dates->match($record, $this->musicBrainzReleaseGroups($record));

                    if ($match) {
                        $original = $match['date'];
                        $source = 'musicbrainz';
                        $musicbrainzId = $match['id'];

                        $matched++;
                    }
                }
            }

            $rows[] = [
                'discogs_release_id' => $record['id'],
                'original_release_date' => $original,
                'original_date_source' => $source,
                'pressing_release_date' => $this->pressingDate($record, $dates),
                'musicbrainz_id' => $musicbrainzId,
                'created_at' => $existing->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, 100) as $chunk) {
                RecordReleaseDate::query()->upsert(
                    $chunk,
                    ['discogs_release_id'],
                    ['original_release_date', 'original_date_source', 'pressing_release_date', 'musicbrainz_id', 'updated_at'],
                );
            }
        });

        $this->comment("Stored dates for {$records->count()} releases. {$matched} MusicBrainz matches.");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $record */
    protected function pressingDate(array $record, RecordReleaseDates $dates): ?string
    {
        $releasePath = storage_path("app/discogs/releases-{$record['id']}.json");

        $release = File::exists($releasePath) ? File::json($releasePath) : [];

        $pressingYear = $record['year'] ? (string) $record['year'] : null;

        return $dates->normalize($release['released'] ?? $record['pressingReleaseDate'] ?? null) ?? $pressingYear;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<int, array<string, mixed>>
     */
    protected function musicBrainzReleaseGroups(array $record): array
    {
        $cachePath = storage_path("app/discogs/musicbrainz-{$record['id']}.json");

        if (File::exists($cachePath)) {
            return File::json($cachePath)['release-groups'] ?? [];
        }

        $this->line("Looking up {$record['artist']} / {$record['displayTitle']} on MusicBrainz...");

        $escape = fn (string $value): string => str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        try {
            $result = Http::withUserAgent(config('services.musicbrainz.user_agent'))
                ->connectTimeout(5)
                ->timeout(15)
                ->get('https://musicbrainz.org/ws/2/release-group/', [
                    'query' => "releasegroup:\"{$escape($record['displayTitle'])}\" AND artist:\"{$escape($record['artists'][0])}\"",
                    'fmt' => 'json',
                    'limit' => 10,
                ]);
        } catch (ConnectionException $exception) {
            $this->warn($exception->getMessage());

            return [];
        }

        Sleep::for(1100)->milliseconds();

        if (! $result->successful()) {
            return [];
        }

        File::ensureDirectoryExists(dirname($cachePath));
        File::put($cachePath, json_encode($result->json(), JSON_THROW_ON_ERROR));

        return $result->json('release-groups') ?? [];
    }
}
