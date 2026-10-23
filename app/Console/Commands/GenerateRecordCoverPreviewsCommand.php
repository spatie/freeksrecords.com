<?php

namespace App\Console\Commands;

use App\Jobs\GenerateRecordCoverPreview;
use App\Models\CollectionRecord;
use Illuminate\Console\Command;

class GenerateRecordCoverPreviewsCommand extends Command
{
    protected $signature = 'records:generate-cover-previews
        {--limit=0 : Maximum previews to queue, or zero for all}
        {--after-id=0 : Resume after this record ID}';

    protected $description = 'Queue missing inline cover previews for existing collections';

    public function handle(): int
    {
        $queued = 0;
        $limit = max(0, (int) $this->option('limit'));

        $records = CollectionRecord::query()->without('release')->selectRaw('MIN(id) as id')
            ->whereHas('release', fn ($query) => $query->whereNull('cover_preview')
                ->whereNotNull('cover')->whereNotIn('cover', ['', '/images/record-placeholder.svg']))
            ->groupBy('discogs_release_id')->havingRaw('MIN(id) > ?', [max(0, (int) $this->option('after-id'))])
            ->orderBy('id')->cursor();

        foreach ($records as $record) {
            $this->line("Queueing cover preview for record {$record->id}...");
            GenerateRecordCoverPreview::dispatch($record->id)->afterCommit();
            $queued++;

            if ($limit > 0 && $queued >= $limit) {
                break;
            }
        }

        $this->info("Queued {$queued} cover previews.");

        return self::SUCCESS;
    }
}
