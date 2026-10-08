@extends('layouts.admin')
@section('title', 'Analytics')

@section('content')
<div class="mb-4 flex flex-wrap items-center gap-2">
    @foreach ([7, 30, 90] as $d)<a href="{{ route('admin.analytics.index', ['days' => $d]) }}" class="chip {{ $days === $d ? 'chip-active' : '' }}">Last {{ $d }} days</a>@endforeach
    <span class="ml-auto text-xs text-ink-3">First-party data only. No raw IP addresses are stored. {{ $ga4 ? 'GA4 is also enabled (consent-gated).' : 'GA4 is not configured.' }}</span>
</div>

<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    <x-admin.stat label="Plays" :value="number_format($totals['plays'])" icon="play"/>
    <x-admin.stat label="Visitor-days" :value="number_format($totals['visitor_days'])" icon="users" hint="Unique visitors per day, summed"/>
    <x-admin.stat label="Avg. session" :value="gmdate($totals['avg_seconds'] >= 3600 ? 'H:i:s' : 'i:s', $totals['avg_seconds'])" icon="clock"/>
    <x-admin.stat label="Failed loads" :value="$totals['plays'] ? round(100 * $totals['failed'] / $totals['plays'], 1).'%' : '—'" icon="alert" :hint="$totals['failed'].' plays'"/>
    <x-admin.stat label="Registrations" :value="number_format($totals['registrations'])" icon="user"/>
    <x-admin.stat label="Searches" :value="number_format($totals['searches'])" icon="search"/>
</div>

<section class="card mt-6 p-4">
    <h2 class="mb-3 font-bold">Plays per day</h2>
    <div class="flex h-40 items-end gap-px" role="img" aria-label="Bar chart of plays per day">
        @foreach ($series as $p)
            <div class="group relative flex-1" title="{{ $p['date'] }}: {{ $p['plays'] }} plays, {{ $p['visitors'] }} visitors, {{ $p['failed'] }} failed">
                <div class="w-full rounded-t bg-brand/70 group-hover:bg-brand-2" style="height: {{ max(1, round(160 * $p['plays'] / $maxPlays)) }}px"></div>
            </div>
        @endforeach
    </div>
    <div class="mt-1 flex justify-between text-[11px] text-ink-3"><span>{{ $series->first()['date'] }}</span><span>{{ $series->last()['date'] }}</span></div>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="card p-4">
        <h2 class="mb-3 font-bold">Top games</h2>
        <table class="table">
            <thead><tr><th>Game</th><th class="text-right">Plays</th><th class="text-right">Avg. time</th><th class="text-right">Failed</th></tr></thead>
            <tbody>
            @forelse ($topGames as $row)
                <tr><td>{{ $gameTitles[$row->game_id]?->tr('title', 'en') ?? '#'.$row->game_id }}</td><td class="text-right tabular-nums">{{ number_format($row->plays) }}</td><td class="text-right tabular-nums">{{ gmdate('i:s', (int) $row->avg_s) }}</td><td class="text-right tabular-nums">{{ $row->failed }}</td></tr>
            @empty
                <tr><td colspan="4" class="text-ink-3">No plays in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <section class="grid gap-4 sm:grid-cols-2">
        @foreach (['Devices' => $devices, 'Languages' => $locales, 'Countries' => $countries, 'Referrers' => $referrers] as $label => $data)
            <div class="card p-4">
                <h3 class="mb-2 text-sm font-bold">{{ $label }}</h3>
                @php $sum = max(1, $data->sum()); @endphp
                <ul class="space-y-1.5 text-sm">
                    @forelse ($data as $k => $c)
                        <li><div class="flex justify-between"><span class="truncate">{{ $k }}</span><span class="tabular-nums text-ink-3">{{ $c }}</span></div>
                            <div class="mt-0.5 h-1 rounded bg-card-2"><div class="h-1 rounded bg-brand-2" style="width: {{ round(100 * $c / $sum) }}%"></div></div></li>
                    @empty
                        <li class="text-ink-3">No data.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </section>
    <section class="card p-4">
        <h2 class="mb-3 font-bold">Top searches</h2>
        <ul class="divide-y divide-line text-sm">
            @forelse ($topSearches as $s)<li class="flex justify-between py-1.5"><span>{{ $s->query }}</span><span class="text-ink-3">{{ $s->c }}× · {{ $s->results }} results</span></li>@empty<li class="text-ink-3">No searches.</li>@endforelse
        </ul>
    </section>
    <section class="card p-4">
        <h2 class="mb-1 font-bold">Searches with no results</h2>
        <p class="mb-3 text-xs text-ink-3">Ideas for new games, tags or category names.</p>
        <ul class="divide-y divide-line text-sm">
            @forelse ($zeroSearches as $s)<li class="flex justify-between py-1.5"><span>{{ $s->query }}</span><span class="text-ink-3">{{ $s->c }}×</span></li>@empty<li class="text-ink-3">None.</li>@endforelse
        </ul>
        <h3 class="mt-5 mb-2 text-sm font-bold">Ads in this period</h3>
        <p class="text-sm text-ink-2">{{ number_format((int) $ads?->impressions) }} impressions · {{ number_format((int) $ads?->clicks) }} clicks
            @if ($ads?->impressions) · CTR {{ round(100 * $ads->clicks / $ads->impressions, 2) }}%@endif
            · revenue {{ number_format((float) $ads?->revenue, 2) }} (entered from network reports)</p>
    </section>
</div>
@endsection
