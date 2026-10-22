<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileCollection
{
    /** @var list<array{name: string, count: int, offset: int}> */
    protected array $groups = [];

    /** @var list<array{copyId: int, section: string, key: string}>|null */
    protected ?array $entries = null;

    public function __construct(protected User $user, protected Request $request) {}

    /** @return array{mode: string, group: ?string, query: string} */
    public function state(): array
    {
        return [
            'mode' => in_array($this->request->query('mode'), ['decade', 'genre', 'artist'], true) ? $this->request->query('mode') : 'recent',
            'group' => is_string($this->request->query('group')) ? mb_substr($this->request->query('group'), 0, 255) : null,
            'query' => is_string($this->request->query('q')) ? mb_substr($this->request->query('q'), 0, 200) : '',
        ];
    }

    /** @return list<array{name: string, count: int, offset: int}> */
    public function groups(): array
    {
        $this->index();

        return $this->groups;
    }

    /** @return LengthAwarePaginator<int, non-empty-array<string, mixed>> */
    public function records(): LengthAwarePaginator
    {
        $this->index();
        $perPage = 36;
        $offset = collect($this->groups)->firstWhere('name', $this->state()['group'])['offset'] ?? 0;
        $page = $this->request->has('recordsPage') ? max(1, $this->request->integer('recordsPage')) : intdiv($offset, $perPage) + 1;
        $page = min($page, max(1, (int) ceil(count($this->entries ?? []) / $perPage)));
        $slice = array_slice($this->entries ?? [], ($page - 1) * $perPage, $perPage);
        $copies = $this->user->records()->whereIn('id', array_column($slice, 'copyId'))
            ->with('user')->get(['id', 'user_id', 'instance_id', 'discogs_release_id', 'added_at', 'payload'])->keyBy('id');
        $summaries = app(RecordCollection::class)->profileSummaries(array_map(fn (array $entry) => $copies->get($entry['copyId']), $slice));
        $items = array_map(fn (array $summary, array $entry) => [...$summary, 'section' => $entry['section'], 'key' => $entry['key']], $summaries, $slice);

        return new LengthAwarePaginator($items, count($this->entries ?? []), $perPage, $page, [
            'path' => $this->request->url(), 'pageName' => 'recordsPage',
            'query' => $this->request->only(['mode', 'q', 'group']),
        ]);
    }

    protected function index(): void
    {
        if ($this->entries !== null) {
            return;
        }

        $state = $this->state();
        $fields = ['artist', 'artists', 'title', 'year', 'originalYear', 'genres'];
        if ($state['query'] !== '') {
            $fields = [...$fields, 'label', 'styles', 'tracks'];
        }
        // Read only small indexing fields; hydrate full payloads for the requested page only.
        $rows = DB::table('collection_records')->where('user_id', $this->user->id)
            ->select(['id as copyId', 'discogs_release_id as id', 'added_at', ...array_map(fn ($field) => "payload->{$field} as {$field}", $fields)])
            ->orderByDesc('added_at')->orderByDesc('collection_records.id')->get();
        $metadata = $rows->map(function (object $row): array {
            $record = (array) $row;
            foreach (['artists', 'genres', 'styles', 'tracks'] as $field) {
                $record[$field] = json_decode($record[$field] ?? '[]', true) ?? [];
            }
            $record['originalYear'] = $record['originalYear'] ? (int) $record['originalYear'] : null;
            $record['year'] = (int) $record['year'];

            return $record;
        })->all();
        $metadata = app(RecordReleaseDates::class)->enrich($metadata);
        $terms = preg_split('/\s+/', Str::lower(Str::ascii(trim($state['query']))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sections = [];
        foreach ($metadata as $record) {
            if ($terms !== []) {
                $text = Str::lower(Str::ascii(implode(' ', [
                    $record['artist'], $record['title'], $record['label'], $record['originalYear'] ?? '', $record['year'],
                    ...$record['genres'], ...$record['styles'], ...array_column($record['tracks'], 'title'),
                ])));
                foreach ($terms as $term) {
                    if (! str_contains($text, $term)) {
                        continue 2;
                    }
                }
            }
            $names = match ($state['mode']) {
                'artist' => $record['artists'],
                'genre' => $record['genres'] ?: ['Unclassified'],
                'decade' => [$record['originalYear'] ? (intdiv((int) $record['originalYear'], 10) * 10).'s' : 'Unknown year'],
                default => [CarbonImmutable::parse($record['added_at'], 'UTC')->setTimezone('Europe/Brussels')->toDateString()],
            };
            foreach (array_unique($names) as $name) {
                $sections[$name][] = $record;
            }
        }
        uksort($sections, fn ($a, $b) => match ($state['mode']) {
            'recent' => strcmp($b, $a),
            'decade' => (int) $b <=> (int) $a,
            default => strnatcasecmp(Str::ascii($a), Str::ascii($b)),
        });
        $this->entries = [];
        foreach ($sections as $name => $records) {
            $name = (string) $name;
            if (in_array($state['mode'], ['artist', 'decade'], true)) {
                usort($records, function ($a, $b) use ($state): int {
                    $dates = ($a['originalReleaseDate'] ?? ($state['mode'] === 'artist' ? '' : '9999')) <=> ($b['originalReleaseDate'] ?? ($state['mode'] === 'artist' ? '' : '9999'));

                    return ($state['mode'] === 'artist' ? -$dates : $dates)
                        ?: strnatcasecmp($a['title'], $b['title']) ?: $a['copyId'] <=> $b['copyId'];
                });
            }
            $this->groups[] = ['name' => $name, 'count' => count($records), 'offset' => count($this->entries)];
            foreach ($records as $record) {
                $this->entries[] = ['copyId' => $record['copyId'], 'section' => $name, 'key' => $name.':'.$record['copyId']];
            }
        }
    }
}
