<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Social preview images live on the public disk, so crawlers fetch them
 * straight from object storage instead of from the app.
 */
class StoredShareImages
{
    protected int $version = 2;

    public function __construct(
        protected RecordCollection $collection,
        protected RecordShareImage $image,
    ) {}

    public function collectionUrl(): string
    {
        $records = $this->collection->latest(5);

        $path = $this->path('collection', array_column($records, 'cover'));

        return $this->store($path, fn (): string => $this->image->renderCollection($records));
    }

    /** @param array<string, mixed> $record */
    public function recordUrl(array $record): string
    {
        $renderedFields = Arr::only($record, ['cover', 'artist', 'displayTitle', 'originalYear']);

        $path = $this->path("records/{$record['instanceId']}", $renderedFields);

        return $this->store($path, fn (): string => $this->image->render($record));
    }

    /** @param array<int|string, mixed> $renderedFields */
    protected function path(string $name, array $renderedFields): string
    {
        $hash = substr(hash('sha256', json_encode([$this->version, $renderedFields], JSON_THROW_ON_ERROR)), 0, 16);

        return "share/{$name}-{$hash}.jpg";
    }

    /** @param Closure(): string $render */
    protected function store(string $path, Closure $render): string
    {
        $disk = Storage::disk('public');

        Cache::rememberForever("stored-share-image:{$path}", function () use ($disk, $path, $render): bool {
            if ($disk->exists($path)) {
                return true;
            }

            $stored = $disk->put($path, $render(), [
                'ContentType' => 'image/jpeg',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);

            if (! $stored) {
                throw new RuntimeException("The share image [{$path}] could not be stored.");
            }

            return true;
        });

        return $disk->url($path);
    }
}
