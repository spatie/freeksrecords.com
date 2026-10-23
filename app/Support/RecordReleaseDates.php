<?php

namespace App\Support;

use App\Models\RecordReleaseDate;
use Illuminate\Support\Str;

class RecordReleaseDates
{
    public function normalize(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        if (! preg_match('/^([1-9][0-9]{3})(?:-([0-9]{2})(?:-([0-9]{2}))?)?$/', $date, $parts)) {
            return null;
        }

        $year = (int) $parts[1];
        $month = (int) ($parts[2] ?? 0);
        $day = (int) ($parts[3] ?? 0);

        if ($month === 0) {
            return $day === 0 ? (string) $year : null;
        }

        if (! checkdate($month, max(1, $day), $year)) {
            return null;
        }

        return $day === 0 ? sprintf('%04d-%02d', $year, $month) : $date;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{
     *     date: string,
     *     id: string
     * }|null
     */
    public function match(array $record, array $candidates): ?array
    {
        $expectedTitle = $this->comparable($record['displayTitle']);

        $expectedArtists = array_map($this->comparable(...), $record['artists']);
        sort($expectedArtists);

        $matches = [];

        foreach ($candidates as $candidate) {
            if ((int) ($candidate['score'] ?? 0) < 95) {
                continue;
            }

            if ($this->comparable($candidate['title'] ?? '') !== $expectedTitle) {
                continue;
            }

            $artists = array_map(
                fn (array $credit): string => $this->comparable($credit['artist']['name'] ?? ''),
                $candidate['artist-credit'] ?? [],
            );
            sort($artists);

            if ($artists !== $expectedArtists) {
                continue;
            }

            $date = $this->normalize($candidate['first-release-date'] ?? null);

            if (! $date) {
                continue;
            }

            if ($record['originalYear']) {
                if ($this->yearOf($date) !== $record['originalYear']) {
                    continue;
                }
            }

            $matches[] = ['date' => $date, 'id' => $candidate['id']];
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    public function enrich(array $records): array
    {
        $dates = RecordReleaseDate::query()
            ->whereIn('discogs_release_id', array_column($records, 'id'))
            ->get()
            ->keyBy('discogs_release_id');

        return array_map(
            fn (array $record): array => $this->withDates($record, $dates->get($record['id'])),
            $records,
        );
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function enrichOne(array $record): array
    {
        return $this->enrich([$record])[0];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function withDates(array $record, ?RecordReleaseDate $date): array
    {
        $discogsYear = $record['originalYear'] ? (string) $record['originalYear'] : null;
        $original = $date?->original_release_date;

        if ($original) {
            if ($record['originalYear']) {
                if ($this->yearOf($original) !== $record['originalYear']) {
                    $original = $discogsYear;
                }
            }
        }

        $pressingYear = $record['year'] ? (string) $record['year'] : null;

        $record['originalReleaseDate'] = $original ?? $discogsYear;
        $record['pressingReleaseDate'] = $date->pressing_release_date ?? $pressingYear;
        $record['originalDateSource'] = $date->original_date_source ?? ($discogsYear ? 'discogs' : null);
        $record['musicbrainzId'] = $record['musicbrainzId'] ?? $date?->musicbrainz_id;
        $record['originalYear'] ??= $original ? $this->yearOf($original) : null;

        return $record;
    }

    protected function comparable(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value))) ?? '';
    }

    protected function yearOf(string $date): int
    {
        return (int) substr($date, 0, 4);
    }
}
