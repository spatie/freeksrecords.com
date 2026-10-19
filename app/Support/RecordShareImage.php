<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RecordShareImage
{
    /** @param array<int, array<string, mixed>> $records */
    public function renderCollection(array $records): string
    {
        $image = $this->paperCanvas();
        $paper = $this->color($image, 232, 221, 196);
        $ink = $this->color($image, 38, 39, 32);
        $woodEdge = $this->color($image, 167, 122, 75);

        $wood = $this->asset(resource_path('images/walnut.jpg'));
        imagecopyresampled($image, $wood, 24, 284, 0, 0, 1152, 322, imagesx($wood), imagesy($wood));
        imagefilledrectangle($image, 36, 296, 1164, 591, $this->color($image, 15, 8, 3, 54));
        imageline($image, 24, 284, 1176, 284, $woodEdge);
        imageline($image, 36, 590, 1164, 590, $woodEdge);

        imagettftext($image, 68, 0, 44, 170, $ink, public_path('fonts/barlow-condensed-bold.ttf'), 'Plaat Club.');

        $stereo = $this->asset(resource_path('images/listening-corner.png'));
        imagecopyresampled($image, $stereo, 556, 65, 0, 0, 618, 206, imagesx($stereo), imagesy($stereo));

        $shadow = $this->color($image, 12, 7, 4, 35);

        foreach (array_values($records) as $index => $record) {
            $cover = $this->cover($record['cover']);

            if (! $cover) {
                continue;
            }

            $left = 53 + $index * 222;

            imagefilledrectangle($image, $left + 5, 336, $left + 216, 547, $shadow);
            imagefilledrectangle($image, $left - 2, 326, $left + 210, 538, $paper);
            imagecopyresampled($image, $cover, $left, 328, 0, 0, 208, 208, imagesx($cover), imagesy($cover));
        }

        return $this->toJpeg($image);
    }

    /** @param array<string, mixed> $record */
    public function render(array $record): string
    {
        $image = $this->paperCanvas();
        $paper = $this->color($image, 232, 221, 196);
        $ink = $this->color($image, 38, 39, 32);
        $muted = $this->color($image, 105, 99, 81);
        $accent = $this->color($image, 157, 53, 34);
        $groove = $this->color($image, 53, 54, 45);

        imagefilledrectangle($image, 0, 0, 16, 630, $accent);
        imagefilledellipse($image, 340, 278, 454, 454, $ink);

        for ($diameter = 444; $diameter > 156; $diameter -= 7) {
            imageellipse($image, 340, 278, $diameter, $diameter, $groove);
        }

        $cover = $this->cover($record['cover']);

        imagefilledellipse($image, 340, 278, 144, 144, $cover ? $this->labelColor($image, $cover) : $accent);
        imagefilledellipse($image, 340, 278, 10, 10, $paper);
        imagefilledrectangle($image, 48, 180, 412, 544, $this->color($image, 85, 70, 49));
        imagefilledrectangle($image, 43, 174, 403, 534, $paper);

        if ($cover) {
            imagecopyresampled($image, $cover, 44, 175, 0, 0, 358, 358, imagesx($cover), imagesy($cover));
        }

        $display = public_path('fonts/barlow-condensed-bold.ttf');
        $body = public_path('fonts/plex-sans-regular.ttf');

        imagettftext($image, 17, 0, 614, 83, $accent, $body, 'PLAAT CLUB');
        imageline($image, 614, 108, 1140, 108, $muted);

        $baseline = 162;

        foreach (array_slice($this->wrap($record['artist'], $body, 22, 510), 0, 2) as $line) {
            imagettftext($image, 22, 0, 614, $baseline, $muted, $body, $line);

            $baseline += 32;
        }

        [$titleSize, $titleLines] = $this->fitTitle($record['displayTitle'], $display);

        $baseline += 37;

        foreach (array_slice($titleLines, 0, 5) as $line) {
            imagettftext($image, $titleSize, 0, 612, $baseline, $ink, $display, $line);

            $baseline += (int) round($titleSize * 1.16);
        }

        $year = $record['originalYear'] ? (string) $record['originalYear'] : '';

        imagettftext($image, 18, 0, 614, 543, $accent, $body, $year);

        return $this->toJpeg($image);
    }

    protected function paperCanvas(): GdImage
    {
        $image = imagecreatetruecolor(1200, 630);

        imagefill($image, 0, 0, $this->color($image, 232, 221, 196));

        $grain = $this->asset(public_path('images/paper-grain.png'));
        imagesettile($image, $grain);
        imagefilledrectangle($image, 0, 0, 1200, 630, IMG_COLOR_TILED);

        return $image;
    }

    protected function labelColor(GdImage $image, GdImage $cover): int
    {
        $sample = imagecolorat($cover, (int) (imagesx($cover) * .7), (int) (imagesy($cover) * .2));

        return $this->color($image, ($sample >> 16) & 255, ($sample >> 8) & 255, $sample & 255);
    }

    /** @return array{0: int, 1: array<int, string>} */
    protected function fitTitle(string $title, string $font): array
    {
        $size = 52;
        $lines = $this->wrap($title, $font, $size, 520);

        while (count($lines) > 4) {
            if ($size <= 28) {
                break;
            }

            $size -= 2;
            $lines = $this->wrap($title, $font, $size, 520);
        }

        return [$size, $lines];
    }

    protected function toJpeg(GdImage $image): string
    {
        imageinterlace($image, true);

        ob_start();
        imagejpeg($image, null, 82);

        return ob_get_clean() ?: '';
    }

    /**
     * @param  int<0, 255>  $red
     * @param  int<0, 255>  $green
     * @param  int<0, 255>  $blue
     * @param  int<0, 127>  $alpha
     */
    protected function color(GdImage $image, int $red, int $green, int $blue, int $alpha = 0): int
    {
        $color = imagecolorallocatealpha($image, $red, $green, $blue, $alpha);

        if ($color === false) {
            throw new RuntimeException('Unable to allocate a social image color.');
        }

        return $color;
    }

    protected function asset(string $path): GdImage
    {
        $bytes = @file_get_contents($path);
        $image = $bytes ? @imagecreatefromstring($bytes) : false;

        if ($image === false) {
            throw new RuntimeException("Unable to load social image asset [{$path}].");
        }

        return $image;
    }

    protected function cover(string $path): ?GdImage
    {
        $bytes = $this->coverBytes($path);

        if (! $bytes) {
            return null;
        }

        return @imagecreatefromstring($bytes) ?: null;
    }

    protected function coverBytes(string $path): ?string
    {
        $disk = Storage::disk('public');
        $storedPrefix = rtrim($disk->url('records'), '/').'/';

        if (str_starts_with($path, $storedPrefix)) {
            $filename = substr($path, strlen($storedPrefix));

            if (! preg_match('/^[0-9]+\.jpg$/', $filename)) {
                return null;
            }

            return $disk->get("records/{$filename}");
        }

        if (RecordCoverImages::isBundledCover($path)) {
            return @file_get_contents(public_path(ltrim($path, '/'))) ?: null;
        }

        if (! RecordCoverImages::isDiscogsCover($path)) {
            return null;
        }

        try {
            $response = Http::connectTimeout(3)->timeout(5)->get($path);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    /** @return array<int, string> */
    protected function wrap(string $text, string $font, int $size, int $width): array
    {
        $lines = [];
        $line = '';

        foreach (explode(' ', $text) as $word) {
            $candidate = trim("{$line} {$word}");
            $box = imagettfbbox($size, 0, $font, $candidate);

            if ($box === false) {
                throw new RuntimeException('Unable to measure social image text.');
            }

            if ($line !== '') {
                if ($width < $box[2] - $box[0]) {
                    $lines[] = $line;
                    $line = $word;

                    continue;
                }
            }

            $line = $candidate;
        }

        $lines[] = $line;

        return $lines;
    }
}
