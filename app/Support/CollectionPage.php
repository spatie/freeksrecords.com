<?php

namespace App\Support;

use Closure;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CollectionPage
{
    public function __construct(
        protected RecordCollection $collection,
        protected StoredShareImages $shareImages,
    ) {}

    /** @param array<string, mixed>|null $sharedRecord */
    public function render(?array $sharedRecord = null): Response
    {
        return Inertia::render('welcome', [
            'sharedRecord' => $sharedRecord ? $this->sharedRecordMeta($sharedRecord) : null,
            'social' => $this->socialMeta(),
            'collection' => [
                'records' => $this->collection->summaries(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function sharedRecordMeta(array $record): array
    {
        return [
            'instanceId' => $record['instanceId'],
            'title' => $record['displayTitle'],
            'artist' => $record['artist'],
            'description' => $record['shareDescription'],
            'url' => url($record['shareUrl']),
            'image' => $this->shareImageUrl(
                fn (): string => $this->shareImages->recordUrl($record),
                route('recordShareImage', ['instanceId' => $record['instanceId'], 'v' => 5]),
            ),
        ];
    }

    /** @return array<string, string> */
    protected function socialMeta(): array
    {
        return [
            'title' => 'Plaat Club',
            'description' => "A lifetime of digging, one shelf at a time. Browse Freek's vinyl collection by artist, decade or genre, and find your next favourite record.",
            'url' => route('collection'),
            'image' => $this->shareImageUrl(
                fn (): string => $this->shareImages->collectionUrl(),
                route('collectionShareImage', ['v' => 4]),
            ),
            'imageAlt' => 'Plaat Club: a wooden shelf of album sleeves beneath a vintage turntable and speakers',
        ];
    }

    /**
     * A failing share image should never take the page down with it.
     *
     * @param  Closure(): string  $storedUrl
     */
    protected function shareImageUrl(Closure $storedUrl, string $fallbackUrl): string
    {
        try {
            return $storedUrl();
        } catch (Throwable $exception) {
            report($exception);

            return $fallbackUrl;
        }
    }
}
