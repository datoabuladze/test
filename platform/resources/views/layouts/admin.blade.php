@php
    $user = auth()->user();
    $nav = [
        ['admin.dashboard', 'chart', 'Dashboard', 'admin.access'],
        ['admin.games.index', 'gamepad', 'Games', 'games.manage'],
        ['admin.categories.index', 'folder', 'Categories', 'categories.manage'],
        ['admin.tags.index', 'tag', 'Tags', 'categories.manage'],
        ['admin.imports.index', 'upload', 'Imports', 'imports.manage'],
        ['admin.providers.index', 'link', 'Providers', 'imports.manage'],
        ['admin.reports.index', 'flag', 'Reports', 'reports.manage'],
        ['admin.homepage.index', 'layout', 'Homepage', 'content.manage'],
        ['admin.pages.index', 'file', 'Pages & blog', 'content.manage'],
        ['admin.menus.index', 'menu', 'Menus', 'content.manage'],
        ['admin.design.index', 'palette', 'Design', 'design.manage'],
        ['admin.ads.index', 'ad', 'Advertising', 'ads.manage'],
        ['admin.seo.index', 'search', 'SEO', 'seo.manage'],
        ['admin.users.index', 'users', 'Users', 'users.moderate'],
        ['admin.analytics.index', 'chart', 'Analytics', 'analytics.view'],
        ['admin.translations.index', 'globe', 'Translations', 'settings.manage'],
        ['admin.settings.index', 'settings', 'Settings', 'settings.manage'],
        ['admin.audit.index', 'shield', 'Audit log', 'audit.view'],
    ];
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') · {{ config('platform.brand') }} admin</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script nonce="{{ $nonce }}">try { const t = localStorage.getItem('theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}</script>
    @include('layouts.partials.theme-vars', ['theme' => \App\Models\ThemeVersion::activeTokens()])
    @stack('head')
    @vite(['resources/js/admin.js'])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh" x-data="disclosure">
<div class="flex min-h-dvh">
    <aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto border-r border-line bg-bg-2 transition lg:translate-x-0" :class="{ '!translate-x-0': open }">
        <div class="flex h-16 items-center px-5"><a href="{{ route('admin.dashboard') }}"><x-logo class="h-7">Admin</x-logo></a></div>
        <nav class="space-y-0.5 px-3 pb-6">
            @foreach ($nav as [$route, $icon, $label, $perm])
                @if ($user->hasPermission($perm))
                    @php $active = request()->routeIs(\Illuminate\Support\Str::beforeLast($route, '.').'*'); @endphp
                    <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm {{ $active ? 'bg-brand/15 font-semibold text-ink' : 'text-ink-2 hover:bg-card hover:text-ink' }}">
                        <x-icon :name="$icon" class="size-4"/>{{ $label }}
                    </a>
                @endif
            @endforeach
            <a href="{{ route('home', ['locale' => 'en']) }}" class="mt-4 flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-ink-3 hover:text-ink"><x-icon name="external" class="size-4"/>View site</a>
        </nav>
    </aside>
    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line bg-bg/80 px-4 backdrop-blur-xl sm:px-6">
            <button type="button" class="flex size-9 items-center justify-center rounded-lg hover:bg-card lg:hidden" @click="toggle" aria-label="Menu"><x-icon name="menu"/></button>
            <h1 class="truncate text-lg font-bold">@yield('title', 'Admin')</h1>
            <div class="ml-auto flex items-center gap-3 text-sm">
                <span class="hidden text-ink-3 sm:inline">{{ $user->nickname }} · {{ $user->role->label() }}</span>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="btn-ghost btn-sm"><x-icon name="logout" class="size-4"/>Log out</button></form>
            </div>
        </header>
        <main class="flex-1 p-4 sm:p-6">
            @if (session('status'))
                <div class="mb-4 rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok" role="status">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-xl border border-bad/30 bg-bad/10 px-4 py-3 text-sm text-bad" role="alert">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-bad/30 bg-bad/10 px-4 py-3 text-sm text-bad" role="alert">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
