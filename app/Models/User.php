<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'disabled_at' => 'datetime',
            'app_limit' => 'integer',
            'connection_limit' => 'integer',
        ];
    }

    /**
     * The signed-in user, or null outside a request — the Reverb server, the
     * CLI and the queue act on everyone's applications, not one user's.
     */
    public static function current(): ?self
    {
        $user = auth()->user();

        return $user instanceof self ? $user : null;
    }

    protected static function booted(): void
    {
        // Through the model rather than the foreign key's cascade, so the
        // server drops the applications at once instead of at its next
        // reconcile.
        static::deleting(function (User $user): void {
            $user->ownedApps()->get()->each->delete();
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isDisabled()) {
            return false;
        }

        $allowed = config('dashboard.access.emails');

        return empty($allowed) || $this->isListedIn($allowed);
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    /**
     * Locks the account out and takes its applications offline. They stay
     * inactive when the account is enabled again; the owner decides which to
     * turn back on.
     */
    public function disable(): void
    {
        $this->forceFill(['disabled_at' => now()])->save();

        $this->ownedApps()->where('active', true)->get()->each->update(['active' => false]);
    }

    public function enable(): void
    {
        $this->forceFill(['disabled_at' => null])->save();
    }

    /**
     * Every application this user owns, whoever is signed in. apps() would
     * narrow to what the signed-in user may see.
     */
    protected function ownedApps(): Builder
    {
        return App::withoutGlobalScopes()->whereBelongsTo($this);
    }

    /**
     * Admins count as verified, so opening registration cannot lock out an
     * operator whose account was made from the CLI.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->isAdmin() || parent::hasVerifiedEmail();
    }

    /**
     * Admins see every application, including those made from the CLI,
     * which belong to no one.
     */
    public function isAdmin(): bool
    {
        return $this->isListedIn(config('dashboard.access.admins'));
    }

    public function apps(): HasMany
    {
        return $this->hasMany(App::class);
    }

    /**
     * How many applications this user may have. Null is unlimited.
     */
    public function appLimit(): ?int
    {
        return $this->quota($this->app_limit, 'apps');
    }

    /**
     * The most connections each of this user's applications may hold. Null
     * is unlimited.
     */
    public function connectionLimit(): ?int
    {
        return $this->quota($this->connection_limit, 'connections');
    }

    protected function quota(?int $override, string $name): ?int
    {
        if (! config('dashboard.quotas.enabled') || $this->isAdmin()) {
            return null;
        }

        return $override ?? (int) config("dashboard.quotas.{$name}");
    }

    /**
     * @param  array<int, string>  $emails
     */
    protected function isListedIn(array $emails): bool
    {
        return collect($emails)->contains(
            fn (string $email): bool => strcasecmp($email, $this->email) === 0,
        );
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  ?array<string>  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
