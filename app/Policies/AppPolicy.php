<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only creation is limited. Filament allows any ability without a method
 * here, and ownership already hides everyone else's applications.
 */
class AppPolicy
{
    public function create(User $user): Response
    {
        $limit = $user->appLimit();

        if ($limit === null || $user->apps()->count() < $limit) {
            return Response::allow();
        }

        return Response::deny(trans_choice(
            'You are using your one application.|You are using all :limit of your applications.',
            $limit,
            ['limit' => $limit],
        ));
    }
}
