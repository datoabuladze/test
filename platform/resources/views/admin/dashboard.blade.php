@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    <x-admin.stat label="Public games" :value="number_format($stats['public'])" icon="gamepad" :hint="number_format($stats['total']).' in catalog'"/>
    <x-admin.stat label="Plays (24h)" :value="number_format($stats['plays_24h'])" icon="play" :hint="$stats['failed_loads_24h'].' failed loads'"/>
    <x-admin.stat label="Users" :value="number_format($stats['users'])" icon="users" :hint="'+'.$stats['users_24h'].' in 24h'"/>
    <x-admin.stat label="Rights unverified" :value="$stats['unverified']" icon="shield"/>
    <x-admin.stat label="Launch failed" :value="$stats['failed']" icon="alert" :hint="$stats['untested'].' untested'"/>
    <x-admin.stat label="Open reports" :value="$stats['open_reports']" icon="flag"/>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="card p-4">
        <h2 class="mb-3 font-bold">Most played (24h)</h2>
        <table class="table">
            <thead><tr><th>Game</th><th>Status</th><th class="text-right">24h</th><th class="text-right">All time</th></tr></thead>
            <tbody>
            @forelse ($topGames as $g)
                <tr>
                    <td><a class="hover:text-brand-2" href="{{ route('admin.games.edit', $g) }}">{{ $g->tr('title') }}</a></td>
                    <td><x-admin.status :value="$g->status"/></td>
                    <td class="text-right tabular-nums">{{ number_format($g->plays_24h) }}</td>
                    <td class="text-right tabular-nums">{{ number_format($g->play_count) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-ink-3">No plays recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="card p-4">
        <h2 class="mb-3 font-bold">Needs attention</h2>
        <table class="table">
            <thead><tr><th>Game</th><th>Rights</th><th>Launch</th></tr></thead>
            <tbody>
            @forelse ($needsAttention as $g)
                <tr>
                    <td><a class="hover:text-brand-2" href="{{ route('admin.games.edit', $g) }}">{{ $g->tr('title') }}</a>
                        @if ($g->last_check_message)<div class="text-xs text-ink-3">{{ \Illuminate\Support\Str::limit($g->last_check_message, 80) }}</div>@endif</td>
                    <td><x-admin.status :value="$g->rights_status"/></td>
                    <td><x-admin.status :value="$g->launch_status"/></td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-ink-3">Nothing needs attention.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="card p-4">
        <div class="mb-3 flex items-center justify-between"><h2 class="font-bold">Open reports</h2>
            @if (auth()->user()->hasPermission('reports.manage'))<a href="{{ route('admin.reports.index') }}" class="text-sm text-brand-2">All reports</a>@endif</div>
        <ul class="divide-y divide-line text-sm">
            @forelse ($reports as $r)
                <li class="py-2"><span class="font-medium">{{ $r->game?->tr('title') ?? 'Deleted game' }}</span> · <span class="text-ink-3">{{ str_replace('_', ' ', $r->reason) }} · {{ $r->created_at->diffForHumans() }}</span></li>
            @empty
                <li class="py-2 text-ink-3">No open reports.</li>
            @endforelse
        </ul>
    </section>

    <section class="card p-4">
        <h2 class="mb-3 font-bold">Recent activity</h2>
        <ul class="divide-y divide-line text-sm">
            @forelse ($activity as $a)
                <li class="flex justify-between gap-3 py-2"><span><span class="font-medium">{{ $a->user?->nickname ?? 'system' }}</span> <code class="text-xs text-ink-2">{{ $a->action }}</code> {{ $a->subject_type }} #{{ $a->subject_id }}</span><span class="shrink-0 text-ink-3">{{ $a->created_at?->diffForHumans() }}</span></li>
            @empty
                <li class="py-2 text-ink-3">No activity yet.</li>
            @endforelse
        </ul>
        @if ($imports->isNotEmpty())
            <h3 class="mt-4 mb-2 text-sm font-semibold">Recent imports</h3>
            <ul class="text-sm">
                @foreach ($imports as $b)
                    <li class="flex justify-between py-1"><span>#{{ $b->id }} {{ $b->provider?->name ?? strtoupper($b->source) }}</span><x-admin.status :value="$b->status"/></li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
