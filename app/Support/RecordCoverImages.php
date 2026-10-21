<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RecordCoverImages
{
    public static function isBundledCover(string $source): bool
    {
        return (bool) preg_match('#^/images/records/[0-9]+\.jpg$#', $source);
    }

    public static function isDiscogsCover(string $source): bool
    {
        if (parse_url($source, PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        return parse_url($source, PHP_URL_HOST) === 'i.discogs.com';
    }

    public function url(int $releaseId): string
    {
        return Storage::disk('public')->url($this->path($releaseId));
    }

    public function copy(int $releaseId, string $source): string
    {
        $disk = Storage::disk('public');
        $path = $this->path($releaseId);

        if ($disk->exists($path)) {
            return $this->url($releaseId);
        }

        $bytes = $this->read($source);

        $stored = $disk->put($path, $bytes, [
            'ContentType' => 'image/jpeg',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        if (! $stored) {
            throw new RuntimeException('The cover could not be stored.');
        }

        return $this->url($releaseId);
    }

    public function read(string $source): string
    {
        return $this->asJpeg($this->sourceBytes($source));
    }

    protected function path(int $releaseId): string
    {
        return "records/{$releaseId}.jpg";
    }

    protected function asJpeg(string $bytes): string
    {
        $dimensions = @getimagesizefromstring($bytes);

        if ($dimensions === false) {
            throw new RuntimeException('The cover is not a supported image.');
        }

        if ($dimensions[0] > 4096 || $dimensions[1] > 4096) {
            throw new RuntimeException('The cover is not a supported image.');
        }

        if ($dimensions['mime'] === 'image/jpeg') {
            return $bytes;
        }

        $image = @imagecreatefromstring($bytes);

        if (! $image) {
            throw new RuntimeException('The cover could not be decoded.');
        }

        ob_start();
        imagejpeg($image, null, 90);

        return ob_get_clean() ?: throw new RuntimeException('The cover could not be converted.');
    }

    protected function sourceBytes(string $source): string
    {
        $disk = Storage::disk('public');
        $storedPrefix = rtrim($disk->url('records'), '/').'/';

        if (str_starts_with($source, $storedPrefix)) {
            $filename = substr($source, strlen($storedPrefix));

            if (! preg_match('/^[0-9]+\.jpg$/', $filename)) {
                throw new RuntimeException('The stored cover path is not supported.');
            }

            return $disk->get("records/{$filename}") ?? throw new RuntimeException('The stored cover could not be read.');
        }

        if (static::isBundledCover($source)) {
            $bytes = file_get_contents(public_path(ltrim($source, '/')));

            if ($bytes === false) {
                throw new RuntimeException('The bundled cover could not be read.');
            }

            return $bytes;
        }

        if (! static::isDiscogsCover($source)) {
            throw new RuntimeException('The cover must come from the bundled collection or Discogs.');
        }

        $bytes = Http::withUserAgent(config('services.discogs.user_agent'))
            ->connectTimeout(5)
            ->timeout(20)
            ->withOptions(['allow_redirects' => false])
            ->get($source)
            ->throw()
            ->body();

        if (strlen($bytes) > 10 * 1024 * 1024) {
            throw new RuntimeException('The cover is too large.');
        }

        return $bytes;
    }
}
