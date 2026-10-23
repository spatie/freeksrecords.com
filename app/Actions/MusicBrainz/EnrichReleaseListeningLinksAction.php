<?php

namespace App\Actions\MusicBrainz;

use App\Actions\Collections\StoreReleaseAction;
use App\Models\Release;
use App\Support\MusicBrainzListeningLinks;
use Illuminate\Support\Facades\DB;

class EnrichReleaseListeningLinksAction
{
    public function __construct(
        protected MusicBrainzListeningLinks $musicbrainz,
        protected StoreReleaseAction $storeRelease,
    ) {}

    public function execute(int $releaseId, bool $refresh = false): void
    {
        $release = Release::query()->find($releaseId);
        if (! $release || (! $refresh && $release->musicbrainz_checked_at !== null)) {
            return;
        }
        $links = $this->musicbrainz->forRelease($release);

        DB::transaction(function () use ($releaseId, $refresh, $links): void {
            $release = Release::query()->lockForUpdate()->find($releaseId);
            if (! $release || (! $refresh && $release->musicbrainz_checked_at !== null)) {
                return;
            }
            $payload = isset($links['musicbrainzId']) ? ['musicbrainzId' => $links['musicbrainzId']] : [];
            foreach (['spotify' => 'spotify', 'appleMusic' => 'apple_music', 'bandcamp' => 'bandcamp'] as $field => $column) {
                if (! isset($links[$field.'Url'])) {
                    continue;
                }
                if (filled($release->getAttribute($column.'_url')) && $release->getAttribute($column.'_search') !== true) {
                    continue;
                }
                $payload[$field.'Url'] = $links[$field.'Url'];
                $payload[$field.'Search'] = false;
            }
            $this->storeRelease->execute($release->id, $payload, replace: true)->update(['musicbrainz_checked_at' => now()]);
        });
    }
}
