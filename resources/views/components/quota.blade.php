{{--
    Above the application list while quotas apply, so a missing "New
    application" button has its reason beside it, and a capped connection
    limit is not a surprise.
--}}
@if ($user?->appLimit() !== null)
    <p style="margin: 0; font-size: 0.875rem; color: var(--gray-500);">
        {{ __(':used of :limit applications used · up to :connections connections each', [
            'used' => $user->apps()->count(),
            'limit' => $user->appLimit(),
            'connections' => $user->connectionLimit(),
        ]) }}
    </p>
@endif
