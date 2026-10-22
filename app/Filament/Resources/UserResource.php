<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->modifyQueryUsing(fn ($query) => $query->withCount('records'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable(['name', 'handle'])->sortable()->description(fn (User $record) => "@{$record->handle}"),
                TextColumn::make('email')->searchable()->copyable()
                    ->icon(fn (User $record) => $record->hasVerifiedEmail() ? 'heroicon-o-check-badge' : null)
                    ->iconColor('success'),
                TextColumn::make('records_count')->label('Records')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Joined')->date('M j, Y')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('email_verified_at')->label('Email verified')->nullable(),
                TernaryFilter::make('discogs_username')->label('Discogs connected')->nullable(),
                SelectFilter::make('visibility')->options(['everyone' => 'Public', 'friends' => 'Friends only', 'only_me' => 'Private']),
            ])
            ->recordActions([ViewAction::make()->label('Details')->icon('heroicon-m-arrow-right')]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')->compact()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('email')->copyable(),
                TextEntry::make('verified')->label('Email status')
                    ->state(fn (User $record) => $record->hasVerifiedEmail() ? 'Verified' : 'Not verified')
                    ->badge()->color(fn (User $record) => $record->hasVerifiedEmail() ? 'success' : 'gray'),
                TextEntry::make('created_at')->label('Joined')->date('M j, Y'),
            ]),
            Section::make('Collection')->compact()->columns(2)->schema([
                TextEntry::make('record_count')->state(fn (User $record) => $record->records()->count())->numeric()->label('Records'),
                TextEntry::make('visibility')->formatStateUsing(fn (string $state) => match ($state) {
                    'everyone' => 'Public', 'friends' => 'Friends only', default => 'Private',
                }),
                TextEntry::make('discogs_username')->label('Discogs')->placeholder('Not connected'),
                TextEntry::make('last_synced_at')->label('Last sync')->since()->placeholder('Never'),
            ]),
            Section::make(fn (User $record) => 'Friends · '.$record->friends()->count())->compact()
                ->schema([
                    ViewEntry::make('friends')->hiddenLabel()->view('filament.users.friends'),
                ]),
            Section::make('More details')->compact()->collapsed()->columnSpanFull()->columns(2)->schema([
                IconEntry::make('digest_enabled')->label('Weekly email enabled')->boolean(),
                TextEntry::make('email_verified_at')->label('Email verified')->dateTime()->placeholder('Not verified'),
                TextEntry::make('collection_imported_at')->label('First import')->dateTime()->placeholder('Not imported'),
                TextEntry::make('sync_status')->label('Sync status')->placeholder('Not synced')->columnSpanFull(),
            ]),
        ]);
    }

    public static function impersonateAction(): Action
    {
        return Action::make('impersonate')->label('View as user')->icon('heroicon-o-user-circle')
            ->requiresConfirmation()
            ->modalHeading('View the site as this user?')
            ->modalDescription('You will be signed in as this collector. Changes you make affect their account. Use “Return to admin” to switch back.')
            ->modalSubmitActionLabel('Start impersonating')
            ->visible(fn (User $record) => $record->id !== auth()->id())
            ->action(function (User $record) {
                abort_unless(auth()->user()?->canAccessPanel(Filament::getPanel('admin')), 403);
                abort_if(session()->has('impersonator_id') || $record->id === auth()->id(), 403);

                session()->put('impersonator_id', auth()->id());
                Auth::login($record);
                session()->forget('password_hash_web');
                session()->regenerate();

                return redirect()->route('home');
            });
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
