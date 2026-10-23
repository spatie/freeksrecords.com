<?php

namespace App\Jobs;

use App\Actions\Discogs\ImportReleaseInfoAction;
use App\Actions\MusicBrainz\EnrichReleaseListeningLinksAction;
use App\Models\Release;
use App\Support\DiscogsRateLimitException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class EnrichRelease implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 600;

    public function __construct(public int $releaseId, public bool $refresh = false)
    {
        $this->onQueue('release-enrichment');
    }

    public function uniqueId(): string
    {
        return (string) $this->releaseId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ImportReleaseInfoAction $importInfo, EnrichReleaseListeningLinksAction $enrichLinks): void
    {
        $release = Release::query()->find($this->releaseId);
        if (! $release) {
            return;
        }

        try {
            if ($release->info_imported_at === null) {
                $importInfo->execute($release->id);
            }

            $enrichLinks->execute($release->id, $this->refresh);
        } catch (DiscogsRateLimitException $exception) {
            $this->release($exception->retryAfter);
        } catch (ConnectionException|RequestException $exception) {
            // HTTP exceptions can contain authorization headers; do not serialize them into failed_jobs.
            $reason = $exception instanceof RequestException ? 'HTTP '.$exception->response->status() : class_basename($exception);

            throw new RuntimeException("Release enrichment failed for {$this->releaseId}: {$reason}.");
        }
    }
}
