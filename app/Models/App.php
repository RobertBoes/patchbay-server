<?php

namespace App\Models;

use Database\Factories\AppFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Gate;
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
            // The policy hides the create button; this stops a second tab,
            // or anything else, from creating past the limit anyway.
            if (User::current()) {
                Gate::authorize('create', static::class);
            }

            $app->user_id ??= User::current()?->getKey();
        });

        static::creating(fn (App $app) => $app->applyQuota());
        static::updating(fn (App $app) => $app->applyQuota());
    }

    /**
     * Holds the application to its owner's quota. Reverb enforces what is
     * stored, so a limit typed into the form is capped here rather than
     * trusted.
     *
     * ponytail: applied on save only; lowering a quota leaves existing
     * applications at the old limit until they are next saved.
     */
    protected function applyQuota(): void
    {
        $limit = $this->user?->connectionLimit();

        if ($limit === null) {
            return;
        }

        $this->max_connections = min($this->max_connections ?? $limit, $limit);

        // The server default is the most a quota'd application may send, so a
        // free account cannot turn the server into a file relay.
        $size = (int) config('patchbay.defaults.max_message_size');
        $this->max_message_size = min($this->max_message_size ?? $size, $size);
    }

    protected static function newFactory(): Factory
    {
        return AppFactory::new();
    }
}
