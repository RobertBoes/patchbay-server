<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admins only. Accounts are made by signing up or from the CLI, so there is
 * no create here: this is for adjusting quotas and dealing with abuse.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'email';

    public static function canAccess(): bool
    {
        return User::current()?->isAdmin() === true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('app_limit')
                    ->label(__('Application limit'))
                    ->numeric()
                    ->minValue(0)
                    ->placeholder(fn (): string => (string) config('dashboard.quotas.apps'))
                    ->helperText(__('Empty uses the configured quota.')),

                TextInput::make('connection_limit')
                    ->label(__('Connections per application'))
                    ->numeric()
                    ->minValue(0)
                    ->placeholder(fn (): string => (string) config('dashboard.quotas.connections'))
                    ->helperText(__('Empty uses the configured quota.')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('apps'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (User $record): string => $record->email),

                TextColumn::make('email')
                    ->searchable()
                    ->hidden(),

                TextColumn::make('apps_count')
                    ->label(__('Applications'))
                    ->sortable(),

                TextColumn::make('quota')
                    ->label(__('Quota'))
                    ->state(fn (User $record): string => static::describeQuota($record)),

                IconColumn::make('email_verified_at')
                    ->label(__('Verified'))
                    ->boolean(),

                TextColumn::make('disabled_at')
                    ->label(__('Status'))
                    ->badge()
                    ->state(fn (User $record): string => $record->isDisabled() ? __('Disabled') : __('Active'))
                    ->color(fn (User $record): string => $record->isDisabled() ? 'danger' : 'success'),

                TextColumn::make('created_at')
                    ->label(__('Joined'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('disabled_at')
                    ->label(__('Disabled'))
                    ->nullable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('Quota'))
                    // Quota columns are deliberately not mass assignable, so
                    // no form a user fills in can raise their own limits.
                    ->using(fn (User $record, array $data): bool => $record->forceFill($data)->save()),

                Action::make('disable')
                    ->label(__('Disable'))
                    ->icon(Heroicon::NoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('They are signed out, and every application they own is deactivated, disconnecting its clients.'))
                    ->visible(fn (User $record): bool => ! $record->isDisabled() && ! $record->isAdmin())
                    ->action(fn (User $record) => $record->disable()),

                Action::make('enable')
                    ->label(__('Enable'))
                    ->icon(Heroicon::CheckCircle)
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->isDisabled())
                    ->action(fn (User $record) => $record->enable()),

                DeleteAction::make()
                    ->modalDescription(__('Their account and every application they own are deleted. Connected clients are disconnected.'))
                    ->hidden(fn (User $record): bool => $record->isAdmin()),
            ]);
    }

    protected static function describeQuota(User $record): string
    {
        if ($record->appLimit() === null) {
            return __('Unlimited');
        }

        return __(':apps apps · :connections connections each', [
            'apps' => $record->appLimit(),
            'connections' => $record->connectionLimit(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
