<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use RobertBoes\Patchbay\Models\Metric as PatchbayMetric;

/**
 * Samples limited to the applications the signed-in user can see, so the
 * dashboard widgets report their traffic rather than the whole server's.
 */
class Metric extends PatchbayMetric
{
    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $query): void {
            if (User::current()?->isAdmin() === false) {
                $query->whereIn('app_id', App::query()->select('id'));
            }
        });
    }
}
