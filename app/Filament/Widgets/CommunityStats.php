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
        $pendingRequests = Friendship::query()->whereNull('accepted_at')->count();
        $failedSyncs = User::query()->where('sync_status', 'like', 'Sync failed.%')->count();

        return [
            Stat::make('Collectors', User::query()->count())
                ->description(User::query()->where('created_at', '>=', now()->subDays(7))->count().' joined in the last 7 days'),
            Stat::make('Records', number_format(CollectionRecord::query()->count()))
                ->description(CollectionRecord::query()->distinct()->count('discogs_release_id').' unique releases'),
            Stat::make('Discogs connected', User::query()->whereNotNull('discogs_username')->count())
                ->description($failedSyncs.' failed '.Str::plural('sync', $failedSyncs)),
            Stat::make('Friendships', Friendship::query()->whereNotNull('accepted_at')->count())
                ->description($pendingRequests.' pending '.Str::plural('request', $pendingRequests)),
            Stat::make('Comments', Comment::query()->count())->description('Conversations around records'),
            Stat::make('Appreciations', DB::table('appreciations')->count())->description('Records loved by the community'),
        ];
    }
}
