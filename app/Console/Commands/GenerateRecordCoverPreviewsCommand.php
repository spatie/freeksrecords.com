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

        $records = CollectionRecord::query()->select('id')->where('id', '>', max(0, (int) $this->option('after-id')))
            ->whereNull('payload->coverPreview')
            ->whereNotNull('payload->cover')->whereNotIn('payload->cover', ['', '/images/record-placeholder.svg'])
            ->lazyById(100);

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
