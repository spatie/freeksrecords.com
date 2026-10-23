<?php

namespace App\Jobs;

use App\Models\CollectionRecord;
use App\Models\Release;
use App\Support\RecordCoverImages;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GenerateRecordCoverPreview implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(public int $recordId) {}

    public function uniqueId(): string
    {
        return (string) $this->recordId;
    }

    public function backoff(): int
    {
        return 120;
    }

    public function handle(RecordCoverImages $images): void
    {
        $record = CollectionRecord::query()->find($this->recordId);
        $source = $record?->payload['cover'] ?? null;

        if (! $source || $source === '/images/record-placeholder.svg' || filled($record->payload['coverPreview'] ?? null)) {
            return;
        }

        $preview = Cache::remember(
            'record-cover-preview:v1:'.hash('sha256', $source),
            now()->addMonth(),
            fn () => $images->preview($source),
        );

        DB::transaction(function () use ($source, $preview): void {
            $record = CollectionRecord::query()->without('release')->find($this->recordId);
            $release = $record ? Release::query()->lockForUpdate()->find($record->discogs_release_id) : null;

            if (! $release || ($release->data['cover'] ?? null) !== $source || filled($release->data['coverPreview'] ?? null)) {
                return;
            }

            $release->update(['data' => [...$release->data, 'coverPreview' => $preview]]);
        });
    }
}
