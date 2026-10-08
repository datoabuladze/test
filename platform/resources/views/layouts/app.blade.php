@php
    /** @var \App\Support\Seo $seo */
    $seo ??= \App\Support\Seo::make();
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" data-theme="{{ ($theme['default_mode'] ?? 'dark') === 'light' ? 'light' : 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b0d17">
    @include('layouts.partials.seo', ['seo' => $seo])
    <link rel="icon" href="{{ ! empty($branding['favicon']) ? asset('storage/'.$branding['favicon']) : asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <script nonce="{{ $nonce }}">
        try { const t = localStorage.getItem('theme'); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @include('layouts.partials.theme-vars')
    {{-- Page-specific modules first: they register Alpine components before app.js starts Alpine. --}}
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.analytics')
</head>
<body class="min-h-dvh">
    <a href="#main" class="sr-only z-50 rounded-lg bg-brand px-4 py-2 text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3">{{ __('Skip to content') }}</a>

    @include('layouts.partials.header')

    <main id="main" class="pb-16">
        @yield('content')
    </main>

    @include('layouts.partials.footer')

    <div x-data="toaster" data-flash="{{ session('status') ?? session('error') }}" class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4">
        <div x-cloak x-show="visible" x-transition.opacity.duration.200ms
             class="pointer-events-auto flex max-w-md items-center gap-3 rounded-xl border border-line bg-card-2 px-4 py-3 text-sm shadow-2xl" role="status" aria-live="polite">
            <x-icon name="info" class="size-4 text-brand-2"/>
            <span x-text="message"></span>
            <button type="button" class="text-ink-3 hover:text-ink" @click="hide" aria-label="{{ __('Close') }}"><x-icon name="x" class="size-4"/></button>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
