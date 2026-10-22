<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages\ListActivities;
use App\Filament\Resources\ActivityResource\Pages\ViewActivity;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $pluralModelLabel = 'Activity log';

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('M j, Y H:i:s', timezone: 'Europe/Brussels')->sortable(),
                TextColumn::make('causer.name')->label('User')->searchable()->placeholder('Deleted user / system'),
                TextColumn::make('description')->label('Activity')->searchable(),
                TextColumn::make('log_name')->label('Category')->badge(),
                TextColumn::make('subject_id')->label('Subject')
                    ->formatStateUsing(fn (Activity $record) => class_basename($record->subject_type)." #{$record->subject_id}"),
            ])
            ->filters([
                SelectFilter::make('log_name')->label('Category')->options(fn () => Activity::query()->distinct()->pluck('log_name', 'log_name')->all()),
                SelectFilter::make('event')->options(fn () => Activity::query()->distinct()->whereNotNull('event')->pluck('event', 'event')->all()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Activity')->columns(2)->columnSpanFull()->schema([
                TextEntry::make('description')->label('Activity'),
                TextEntry::make('created_at')->label('When (Europe/Brussels)')->dateTime('M j, Y H:i:s', timezone: 'Europe/Brussels'),
                TextEntry::make('causer.name')->label('User')->placeholder('Deleted user / system'),
                TextEntry::make('causer_id')->label('User ID')->placeholder('System'),
                TextEntry::make('log_name')->label('Category')->badge(),
                TextEntry::make('event')->badge(),
                TextEntry::make('subject_type')->label('Subject type')->formatStateUsing(fn (string $state) => class_basename($state)),
                TextEntry::make('subject_id')->label('Subject ID'),
                KeyValueEntry::make('properties')->columnSpanFull(),
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }
}
