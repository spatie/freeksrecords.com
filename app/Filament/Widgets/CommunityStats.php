<?php

namespace App\Filament\Widgets;

use App\Models\CollectionRecord;
use App\Models\Comment;
use App\Models\Friendship;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommunityStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|array|null $columns = ['default' => 2, '@xl' => 3, '@4xl' => 6];

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $users = User::query()->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(CASE WHEN created_at >= ? THEN 1 END) AS recent', [now()->subDays(7)])
            ->selectRaw('COUNT(CASE WHEN discogs_username IS NOT NULL THEN 1 END) AS connected')
            ->selectRaw('COUNT(CASE WHEN sync_status LIKE ? THEN 1 END) AS failed', ['Sync failed.%'])->first();
        $friendships = Friendship::query()->selectRaw('COUNT(accepted_at) AS accepted')
            ->selectRaw('COUNT(*) - COUNT(accepted_at) AS pending')->first();
        $pendingRequests = (int) $friendships->getAttribute('pending');
        $failedSyncs = (int) $users->getAttribute('failed');

        return [
            Stat::make('Collectors', $users->getAttribute('total'))
                ->description($users->getAttribute('recent').' joined in the last 7 days'),
            Stat::make('Records', number_format(CollectionRecord::query()->count()))
                ->description(CollectionRecord::query()->distinct()->count('discogs_release_id').' unique releases'),
            Stat::make('Discogs connected', $users->getAttribute('connected'))
                ->description($failedSyncs.' failed '.Str::plural('sync', $failedSyncs)),
            Stat::make('Friendships', $friendships->getAttribute('accepted'))
                ->description($pendingRequests.' pending '.Str::plural('request', $pendingRequests)),
            Stat::make('Comments', Comment::query()->count())->description('Conversations around records'),
            Stat::make('Appreciations', DB::table('appreciations')->count())->description('Records loved by the community'),
        ];
    }
}
