<?php

namespace App\Console\Commands;

use App\Models\Friendship;
use App\Models\SocialNotification;
use App\Models\User;
use App\Support\DiscogsRecordMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        foreach (['robin', 'sam', 'casey', 'alex'] as $index => $handle) {
            foreach (['After the Rain', 'Soft Signals', 'Sunday Windows'] as $number => $title) {
                $instance = 900000 + $index * 10 + $number;
                $added = now()->subDays($number + $index)->toIso8601String();
                $artist = ['The Paper Satellites', 'Harbor Lights', 'Studio Fern', 'The Quiet Hours'][$index];
                $payload = $mapper->record([
                    'instance_id' => $instance, 'date_added' => $added,
                    'basic_information' => ['id' => $instance, 'title' => $title, 'artists' => [['name' => $artist]], 'year' => 2026, 'labels' => [['name' => 'Synthetic demo release']], 'cover_image' => '', 'formats' => [['name' => ['Vinyl', 'CD', 'Cassette'][$number], 'qty' => '1']]],
                ], ['year' => 2026, 'tracklist' => [['title' => 'A Small Beginning', 'position' => 'A1', 'duration' => '4:12']]], []);
                $payload['cover'] = '/images/demo-sleeve-'.($number + 1).'.svg';
                $record = $users[$handle]->records()->firstOrCreate(['instance_id' => $instance], [
                    'discogs_release_id' => $instance, 'added_at' => now()->subDays($number + $index)->utc()->toDateTimeString(),
                    'payload' => $payload, 'is_activity' => $number !== 2,
                    'shared_note' => $handle === 'alex' && $number === 0 ? 'A favourite for a quiet evening. Play it all the way through.' : null,
                    'private_note' => $handle === 'alex' && $number === 0 ? 'Synthetic demo note: a birthday gift. Keep this copy.' : null,
                ]);
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
        $this->info('Alex has a Vinyl/CD/Cassette shelf, shared/private notes, friends, an incoming request, a comment and an appreciation. Jamie has an empty shelf; Casey’s records are private. No OAuth credentials or external email are used.');

        return self::SUCCESS;
    }
}
