@extends('layouts.admin')
@section('title', 'SEO')
@section('content')
<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <x-admin.stat label="Public games" :value="number_format($overview['games_public'])" icon="gamepad"/>
    <x-admin.stat label="Active categories" :value="number_format($overview['categories_active'])" icon="folder"/>
    <x-admin.stat label="Published pages" :value="number_format($overview['pages_published'])" icon="file"/>
</div>

<div class="mb-6 grid gap-6 lg:grid-cols-[1fr_320px]">
    <section class="card overflow-x-auto">
        <header class="border-b border-line px-5 py-3">
            <h2 class="font-semibold">Missing content per language</h2>
            <p class="text-xs text-ink-3">Counts of public items without text in that language (fallback to English is used on the site).</p>
        </header>
        <table class="table">
            <thead><tr><th>Field</th>@foreach ($locales as $code => $meta)<th class="text-right">{{ strtoupper($code) }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ([
                'games_missing_seo' => ['Games · SEO description', route('admin.games.index')],
                'games_missing_description' => ['Games · description', route('admin.games.index')],
                'categories_missing_description' => ['Categories · description', route('admin.categories.index')],
                'pages_missing_seo' => ['Pages · SEO description', route('admin.pages.index')],
            ] as $key => [$label, $href])
                <tr>
                    <td><a href="{{ $href }}" class="hover:text-brand">{{ $label }}</a></td>
                    @foreach ($locales as $code => $meta)
                        @php $n = $overview[$key][$code] ?? 0; @endphp
                        <td class="text-right tabular-nums {{ $n > 0 ? 'text-warn font-semibold' : 'text-ok' }}">{{ $n }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <aside class="card space-y-3 p-5 text-sm">
        <h2 class="font-semibold">Crawling &amp; tools</h2>
        <a href="{{ route('sitemap.index') }}" target="_blank" rel="noopener" class="flex items-center gap-2 hover:text-brand"><x-icon name="external" class="size-4"/>sitemap.xml</a>
        <a href="{{ route('robots') }}" target="_blank" rel="noopener" class="flex items-center gap-2 hover:text-brand"><x-icon name="external" class="size-4"/>robots.txt</a>
        <p class="help">Outside production, robots.txt disallows all crawling so staging is never indexed.</p>
        <div class="border-t border-line pt-3 text-xs text-ink-3 space-y-1">
            <div>Search Console verification: @if ($verification)<span class="badge bg-ok/15 text-ok">set</span>@else<span class="badge bg-card-2 text-ink-3">not set</span>@endif</div>
            <div>GA4: @if ($ga4)<span class="font-mono">{{ $ga4 }}</span>@else<span class="badge bg-card-2 text-ink-3">not set</span>@endif</div>
            <div>Both are configured via environment variables.</div>
        </div>
    </aside>
</div>

<div class="grid gap-6 xl:grid-cols-[1fr_360px]">
    <section class="card overflow-x-auto">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-3">
            <h2 class="font-semibold">Redirects</h2>
            <form method="get" class="flex items-center gap-2">
                <input type="search" name="q" value="{{ $q }}" placeholder="Search" class="input w-48 !py-1.5">
            </form>
        </header>
        <table class="table">
            <thead><tr><th>From</th><th>To</th><th>Code</th><th class="text-right">Hits</th><th></th></tr></thead>
            <tbody>
            @forelse ($redirects as $r)
                <tr>
                    <td class="font-mono text-xs break-all">{{ $r->from_path }}</td>
                    <td class="font-mono text-xs break-all">{{ $r->to_url }}</td>
                    <td><span class="chip !py-0.5">{{ $r->status_code }}</span></td>
                    <td class="text-right tabular-nums">{{ number_format($r->hits) }}</td>
                    <td class="text-right">
                        <form method="post" action="{{ route('admin.redirects.destroy', $r) }}" x-data="confirmForm" data-confirm="Delete this redirect?" @submit="confirmSubmit">
                            @csrf @method('DELETE')
                            <button class="btn-danger btn-sm" title="Delete"><x-icon name="trash" class="size-4"/></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-ink-3">No redirects.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $redirects->links() }}</div>
    </section>

    <aside>
        <form method="post" action="{{ route('admin.redirects.store') }}" class="card sticky top-20 space-y-4 p-5">
            @csrf
            <h2 class="font-semibold">Add redirect</h2>
            <div>
                <label class="label" for="f-from">From path</label>
                <input id="f-from" name="from_path" value="{{ old('from_path') }}" class="input font-mono" required maxlength="512" placeholder="/en/old-game">
                <p class="help">Path on this site starting with “/”. Applies only when the path would otherwise return 404.</p>
            </div>
            <div>
                <label class="label" for="f-to">To</label>
                <input id="f-to" name="to_url" value="{{ old('to_url') }}" class="input font-mono" required maxlength="1024" placeholder="/en/games/new-game or https://…">
                <p class="help">Relative path or https:// URL.</p>
            </div>
            <div>
                <label class="label" for="f-code">Type</label>
                <select id="f-code" name="status_code" class="input">
                    <option value="301" @selected(old('status_code', '301') == '301')>301 – permanent</option>
                    <option value="302" @selected(old('status_code') == '302')>302 – temporary</option>
                </select>
            </div>
            <button class="btn-primary w-full"><x-icon name="plus" class="size-4"/>Add redirect</button>
        </form>
    </aside>
</div>
@endsection
