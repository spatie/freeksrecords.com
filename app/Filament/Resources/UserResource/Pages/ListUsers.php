<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Widgets\CommunityStats;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected ?string $subheading = 'The people and records that make up Plaat Club.';

    /** @return array<class-string<CommunityStats>> */
    protected function getHeaderWidgets(): array
    {
        return [CommunityStats::class];
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }
}
