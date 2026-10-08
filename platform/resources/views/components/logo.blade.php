@props(['class' => 'h-8'])
{{-- Original mark: an orbit ring crossing a glowing play-core. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <svg class="{{ $class }} w-auto" viewBox="0 0 40 40" aria-hidden="true">
        <defs>
            <linearGradient id="lg-core" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="var(--color-brand-2)"/>
                <stop offset=".55" stop-color="var(--color-brand)"/>
                <stop offset="1" stop-color="var(--color-brand-3)"/>
            </linearGradient>
        </defs>
        <circle cx="20" cy="20" r="13" fill="url(#lg-core)"/>
        <path d="M17 14.5v11l9-5.5Z" fill="#fff"/>
        <ellipse cx="20" cy="20" rx="18.5" ry="7" fill="none" stroke="url(#lg-core)" stroke-width="2.2" transform="rotate(-28 20 20)" opacity=".9"/>
        <circle cx="35" cy="12" r="2.2" fill="var(--color-brand-2)"/>
    </svg>
    <span class="font-display text-xl font-extrabold tracking-tight">{{ $slot->isEmpty() ? config('platform.brand') : $slot }}</span>
</span>
