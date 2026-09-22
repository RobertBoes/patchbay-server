<?php

namespace App\Models;

use Database\Factories\AppFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RobertBoes\Patchbay\Models\App as PatchbayApp;

/**
 * A Patchbay application with an owner. Signed-in users only ever see their
 * own; anything without a signed-in user — the Reverb server, the CLI, the
 * queue — sees them all, which the server needs to accept connections.
 */
class App extends PatchbayApp
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('owner', function (Builder $query): void {
            if (User::current()?->isAdmin() === false) {
                $query->whereBelongsTo(User::current());
            }
        });

        static::creating(function (App $app): void {
            $app->user_id ??= User::current()?->getKey();
        });
    }

    protected static function newFactory(): Factory
    {
        return AppFactory::new();
    }
}
