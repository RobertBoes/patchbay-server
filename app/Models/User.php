<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
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

    public function canAccessPanel(Panel $panel): bool
    {
        $allowed = config('dashboard.access.emails');

        return empty($allowed) || $this->isListedIn($allowed);
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
