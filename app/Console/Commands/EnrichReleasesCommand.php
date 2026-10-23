<?php

namespace App\Console\Commands;

use App\Jobs\EnrichRelease;
use App\Models\Release;
use Illuminate\Console\Command;

class EnrichReleasesCommand extends Command
{
    protected $signature = 'records:enrich-releases {--refresh : Recheck MusicBrainz without replacing direct links}';

    protected $description = 'Queue missing Discogs information and MusicBrainz listening links once per shared release';

    public function handle(): int
    {
        $refresh = (bool) $this->option('refresh');
        $query = Release::query();
        if (! $refresh) {
            $query->where(fn ($query) => $query->whereNull('info_imported_at')->orWhereNull('musicbrainz_checked_at'));
        }
        $count = 0;
        $query->select('id')->eachById(function (Release $release) use ($refresh, &$count): void {
            $this->line("Queueing enrichment for release {$release->id}...");
            EnrichRelease::dispatch($release->id, $refresh)->afterCommit();
            $count++;
        });
        $this->comment("Queued {$count} releases. Completed lookups are skipped on the next run.");

        return self::SUCCESS;
    }
}
