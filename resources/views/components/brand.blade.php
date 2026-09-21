{{--
    The panel's brand lockup: the mark beside the name. Inline styles, because
    the panel is served by Filament's own stylesheet and this application has
    no Tailwind build to supply utility classes from.
--}}
<span style="display: inline-flex; align-items: center; gap: 0.6rem; --patchbay-accent: var(--primary-500);">
    <x-logo width="28" height="28" />
    <span style="font-size: 1.25rem; font-weight: 600; letter-spacing: -0.02em;">
        {{ config('dashboard.brand') }}
    </span>
</span>
