<?php

namespace App\Console\Commands;

use App\Models\Friendship;
use App\Models\SocialNotification;
use App\Models\User;
use App\Support\DiscogsRecordMapper;
use Illuminate\Console\Command;

class SeedSocialDemoCommand extends Command
{
    protected $signature = 'social:seed-demo';

    protected $description = 'Seed local-only synthetic demo profiles and shelves without contacting Discogs';

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
                'visibility' => $visibility, 'collection_imported_at' => now(), 'digest_enabled' => false,
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
        foreach (['robin', 'sam', 'casey'] as $index => $handle) {
            foreach (['After the Rain', 'Soft Signals', 'Sunday Windows'] as $number => $title) {
                $instance = 900000 + $index * 10 + $number;
                $added = now()->subDays($number + $index)->toIso8601String();
                $artist = ['The Paper Satellites', 'Harbor Lights', 'Studio Fern'][$index];
                $payload = $mapper->record([
                    'instance_id' => $instance, 'date_added' => $added,
                    'basic_information' => ['id' => $instance, 'title' => $title, 'artists' => [['name' => $artist]], 'year' => 2026, 'labels' => [['name' => 'Synthetic demo release']], 'cover_image' => '', 'formats' => [['name' => ['Vinyl', 'CD', 'Cassette'][$number], 'qty' => '1']]],
                ], ['year' => 2026, 'tracklist' => [['title' => 'A Small Beginning', 'position' => 'A1', 'duration' => '4:12']]], []);
                $payload['cover'] = '/images/demo-sleeve-'.($number + 1).'.svg';
                $users[$handle]->records()->firstOrCreate(['instance_id' => $instance], [
                    'discogs_release_id' => $instance, 'added_at' => now()->subDays($number + $index)->utc()->toDateTimeString(),
                    'payload' => $payload, 'is_activity' => $number !== 2,
                ]);
            }
        }
        $this->info('Local synthetic accounts: alex/robin/sam/casey/jamie@example.test. Password: demo-listening-2026');
        $this->info('Alex has friends, an incoming request, and an empty own shelf. Casey’s records are private. No OAuth credentials or external email are used.');

        return self::SUCCESS;
    }
}
