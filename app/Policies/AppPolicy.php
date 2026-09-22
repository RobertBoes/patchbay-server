<?php

namespace App\Policies;

use App\Models\User;

/**
 * Only creation is limited. Filament allows any ability without a method
 * here, and ownership already hides everyone else's applications.
 */
class AppPolicy
{
    public function create(User $user): bool
    {
        $limit = $user->appLimit();

        return $limit === null || $user->apps()->count() < $limit;
    }
}
