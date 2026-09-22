<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

class EditProfile extends BaseEditProfile
{
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
                ...Arr::wrap($this->getMultiFactorAuthenticationContentComponent()),
                ...Arr::wrap($this->getDeleteAccountContentComponent()),
            ]);
    }

    /**
     * Admins come from DASHBOARD_ADMINS, so an operator removes themselves
     * there rather than from here, and cannot lock the deployment out of its
     * own panel with one click.
     */
    protected function getDeleteAccountContentComponent(): ?Component
    {
        if ($this->getUser()->isAdmin()) {
            return null;
        }

        return Section::make(__('Delete account'))
            ->description(__('Your account and every application you own are deleted at once, and their clients disconnected. This cannot be undone.'))
            ->compact()
            ->secondary()
            ->schema([
                Actions::make([$this->deleteAccountAction()]),
            ]);
    }

    protected function deleteAccountAction(): Action
    {
        return Action::make('deleteAccount')
            ->label(__('Delete account'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('Delete your account?'))
            ->modalDescription(__('Every application you own is deleted with it.'))
            ->modalSubmitActionLabel(__('Delete account'))
            ->schema([
                TextInput::make('currentPassword')
                    ->label(__('Current password'))
                    ->password()
                    ->autocomplete('current-password')
                    ->currentPassword(guard: Filament::getAuthGuard())
                    ->required(),
            ])
            ->action(function (): void {
                $user = $this->getUser();

                Filament::auth()->logout();
                $user->delete();

                session()->invalidate();
                session()->regenerateToken();

                $this->redirect(Filament::getLoginUrl());
            });
    }
}
