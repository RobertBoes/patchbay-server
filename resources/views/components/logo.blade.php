{{--
    The Patchbay mark: a signal and its tail, decaying left to right. The
    struck bar is drawn in --patchbay-accent and the tail in currentColor, so
    one file serves the light panel, the dark panel and the landing page
    without a second copy to keep in step.

    Sized by width and height attributes rather than by utility classes: this
    application ships no Tailwind build of its own, and the mark has to render
    correctly on a deployment where nobody has run one.
--}}
<svg
    {{ $attributes->merge(['width' => 40, 'height' => 40]) }}
    viewBox="0 0 40 40"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    role="img"
    aria-label="{{ config('dashboard.brand') }}"
>
    <g stroke-width="3.4" stroke-linecap="round">
        <path d="M7 8v24" stroke="var(--patchbay-accent, currentColor)"/>
        <path d="M15.5 13v14" stroke="currentColor"/>
        <path d="M24 17.5v5" stroke="currentColor" opacity="0.65"/>
    </g>
    {{-- The last of the tail is a round cap with nothing left to draw, which
         is a dot rather than a line — spelled as one so it renders. --}}
    <circle cx="32.5" cy="20" r="1.7" fill="currentColor" opacity="0.4"/>
</svg>
