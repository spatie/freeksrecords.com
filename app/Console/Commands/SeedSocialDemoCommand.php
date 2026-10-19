<?php

namespace App\Console\Commands;

use App\Models\Friendship;
use App\Models\SocialNotification;
use App\Models\User;
use App\Support\DiscogsRecordMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SeedSocialDemoCommand extends Command
{
    protected $signature = 'social:seed-demo';

    protected $description = 'Seed local-only demo profiles with real catalog shelves without contacting Discogs';

    public function handle(DiscogsRecordMapper $mapper): int
    {
        if (! app()->environment('local')) {
            $this->error('Demo seeding is restricted to APP_ENV=local.');

            return self::FAILURE;
        }
        $users = [];
        foreach (['alex' => 'friends', 'robin' => 'friends', 'sam' => 'everyone', 'casey' => 'only_me', 'jamie' => 'friends'] as $handle => $visibility) {
            $users[$handle] = User::query()->firstOrCreate(['email' => $handle.'@example.test'], [
                'name' => ucfirst($handle).' (demo)', 'handle' => $handle,
                'password' => 'demo-listening-2026', 'email_verified_at' => now(),
                'visibility' => $visibility, 'collection_imported_at' => $handle === 'jamie' ? null : now(), 'digest_enabled' => false,
            ]);
        }
        foreach (['robin', 'sam', 'casey', 'jamie'] as $handle) {
            $friend = Friendship::query()->firstOrCreate([
                'user_low' => min($users['alex']->id, $users[$handle]->id), 'user_high' => max($users['alex']->id, $users[$handle]->id),
            ], ['requested_by' => $users[$handle]->id, 'accepted_at' => $handle === 'jamie' ? null : now()]);
            if ($friend->wasRecentlyCreated && $handle === 'jamie') {
                SocialNotification::send($users['alex'], $users[$handle], 'friend_request');
            }
        }
        $vinyl = File::json(resource_path('data/collection.json'))['records'];
        $editions = File::json(resource_path('data/demo-editions.json'))['releases'];
        foreach (['robin', 'sam', 'casey', 'alex'] as $index => $handle) {
            $count = ['robin' => 90, 'sam' => 60, 'casey' => 30, 'alex' => 120][$handle];
            for ($number = 0; $number < $count; $number++) {
                $title = ['After the Rain', 'Soft Signals', 'Sunday Windows'][$number % 3];
                $instance = $number < 3 ? 900000 + $index * 10 + $number : 910000 + $index * 1000 + $number;
                $date = $number < 12 ? now()->subDays($number % 6)->subHours($index + $number) : now()->subDays(90 + $number * 2);
                $added = $date->toIso8601String();
                $artist = ['The Paper Satellites', 'Harbor Lights', 'Studio Fern', 'The Quiet Hours'][$index];
                $payload = $mapper->record([
                    'instance_id' => $instance, 'date_added' => $added,
                    'basic_information' => ['id' => $instance, 'title' => $title, 'artists' => [['name' => $artist]], 'year' => 2026, 'labels' => [['name' => 'Synthetic demo release']], 'cover_image' => '', 'formats' => [['name' => ['Vinyl', 'CD', 'Cassette'][$number % 3], 'qty' => '1']]],
                ], ['year' => 2026, 'tracklist' => [['title' => 'A Small Beginning', 'position' => 'A1', 'duration' => '4:12']]], []);
                $payload['cover'] = '/images/demo-sleeve-'.($number + 1).'.svg';
                $legacy = $payload;
                if ($number >= 1 && $number <= count($editions)) {
                    $edition = $editions[$number - 1];
                    $payload = $mapper->record(['instance_id' => $instance, 'date_added' => $added, 'basic_information' => $edition['basic_information']], $edition['release'], $edition['master']);
                    $payload['cover'] = $edition['cover'];
                } else {
                    // Sample across the snapshot for varied artists, genres and years.
                    $payload = $vinyl[($index + $number * 7) % count($vinyl)];
                    $payload['instanceId'] = $instance;
                    $payload['addedAt'] = $added;
                    $payload['mediaFormats'] = $mapper->mediaFormats($payload['format']);
                }
                $record = $users[$handle]->records()->firstOrCreate(['instance_id' => $instance], [
                    'discogs_release_id' => $payload['id'], 'added_at' => $date->utc()->toDateTimeString(),
                    'payload' => $payload, 'is_activity' => $number < 12 && $number !== 2,
                    'shared_note' => $handle === 'alex' && $number === 0 ? 'A favourite for a quiet evening. Play it all the way through.' : null,
                    'private_note' => $handle === 'alex' && $number === 0 ? 'Synthetic demo note: a birthday gift. Keep this copy.' : null,
                ]);
                // Match the entire original payload (including its stored date) before upgrading.
                // Notes, dates, activity and social interactions are never replaced.
                if (! $record->wasRecentlyCreated && $number < 3 && $record->discogs_release_id === $instance) {
                    $legacy['addedAt'] = $record->payload['addedAt'] ?? '';
                    if ($record->payload == $legacy) {
                        $payload['addedAt'] = $legacy['addedAt'];
                        $record->update(['discogs_release_id' => $payload['id'], 'payload' => $payload]);
                    }
                }
                if ($handle === 'alex' && $number === 0) {
                    $comment = $record->comments()->firstOrCreate(['user_id' => $users['robin']->id, 'body' => 'This sounds like a perfect Sunday listen. Great find!']);
                    if ($comment->wasRecentlyCreated) {
                        SocialNotification::send($users['alex'], $users['robin'], 'comment', $record);
                    }
                    if (DB::table('appreciations')->insertOrIgnore(['user_id' => $users['sam']->id, 'collection_record_id' => $record->id])) {
                        SocialNotification::send($users['alex'], $users['sam'], 'appreciation', $record);
                    }
                }
            }
        }
        $this->info('Local synthetic accounts: alex/robin/sam/casey/jamie@example.test. Password: demo-listening-2026');
        $this->info('Real catalog shelves: Alex 120, Robin 90, Sam 60, Casey 30; Jamie stays empty. Existing edits and privacy are preserved. No Discogs requests, OAuth credentials or external email are used.');

        return self::SUCCESS;
    }
}
