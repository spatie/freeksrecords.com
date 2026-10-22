<?php

namespace App\Console\Commands;

use App\Models\CollectionRecord;
use App\Support\Discogs;
use App\Support\DiscogsRateLimitException;
use App\Support\DiscogsRecordMapper;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

class ImportDiscogsInfoCommand extends Command
{
    protected $signature = 'records:import-discogs-info';

    protected $description = 'Import missing artist biographies, release notes and credits without changing collection data';

    public function handle(Discogs $discogs, DiscogsRecordMapper $mapper): int
    {
        $releaseIds = CollectionRecord::query()
            ->whereNull('payload->releaseNotes')
            ->distinct()->pluck('discogs_release_id');
        $imported = 0;

        foreach ($releaseIds as $releaseId) {
            $this->info("Importing Discogs information for release {$releaseId}...");

            try {
                $release = $discogs->get("releases/{$releaseId}");
                $info = $mapper->releaseInfo($release, $discogs->artistBios($release['artists'] ?? []));
            } catch (ConnectionException|RequestException|DiscogsRateLimitException $exception) {
                $reason = $exception instanceof RequestException ? 'HTTP '.$exception->response->status() : class_basename($exception);
                $this->error("Import stopped: {$reason}. Saved information was kept; rerun this command to resume.");

                return self::FAILURE;
            }

            DB::transaction(function () use ($releaseId, $info): void {
                CollectionRecord::query()->where('discogs_release_id', $releaseId)
                    ->whereNull('payload->releaseNotes')->lockForUpdate()->eachById(function (CollectionRecord $record) use ($info): void {
                        $record->update(['payload' => [...$record->payload, ...$info]]);
                    });
            });
            $imported++;
        }

        $this->comment("Imported information for {$imported} releases.");

        return self::SUCCESS;
    }
}
