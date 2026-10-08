@extends('layouts.admin')
@section('title', 'Advertising')
@section('content')
@php
    $ctr = fn ($i, $c) => $i > 0 ? number_format($c / $i * 100, 2).'%' : '—';
@endphp
<div class="mb-6 rounded-xl border border-line bg-card-2 px-4 py-3 text-sm text-ink-2" role="note">
    <div class="flex items-start gap-3">
        <x-icon name="info" class="mt-0.5 size-5 shrink-0 text-brand-2"/>
        <div class="space-y-1">
            <p><strong>Google AdSense</strong> campaigns only render once your site is approved and <code class="font-mono">ADSENSE_CLIENT</code> is set in the server environment.
                Current status: @if ($adsenseClient)<span class="badge bg-ok/15 text-ok">configured</span>@else<span class="badge bg-warn/15 text-warn">not configured – AdSense slots stay empty</span>@endif</p>
            <p>The platform never simulates impressions or clicks. Impressions are counted when an ad is actually served in a page; clicks when a visitor follows a direct or sponsored ad. AdSense reporting lives in your AdSense account. Ads are never placed over the game player.</p>
        </div>
    </div>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <x-admin.stat label="Impressions (30 days)" :value="number_format((int) $totals->impressions)" icon="eye"/>
    <x-admin.stat label="Clicks (30 days)" :value="number_format((int) $totals->clicks)" icon="mouse"/>
    <x-admin.stat label="CTR (30 days)" :value="$ctr((int) $totals->impressions, (int) $totals->clicks)" icon="chart"/>
</div>

<section class="card mb-6 overflow-x-auto">
    <header class="border-b border-line px-5 py-3"><h2 class="font-semibold">Placements</h2></header>
    <table class="table">
        <thead><tr><th>Placement</th><th>Key</th><th>Size</th><th>Campaigns</th><th class="text-right">Status</th></tr></thead>
        <tbody>
        @foreach ($placements as $p)
            <tr>
                <td class="font-medium">{{ $p->name }}</td>
                <td class="font-mono text-xs text-ink-3">{{ $p->key }}</td>
                <td class="text-xs text-ink-3">{{ $p->size_hint ?? '—' }}</td>
                <td class="tabular-nums">{{ $p->campaigns_count }}</td>
                <td class="text-right">
                    <form method="post" action="{{ route('admin.ads.placement', $p) }}" class="inline-flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="is_enabled" value="{{ $p->is_enabled ? 0 : 1 }}">
                        <span class="badge {{ $p->is_enabled ? 'bg-ok/15 text-ok' : 'bg-card-2 text-ink-3' }}">{{ $p->is_enabled ? 'enabled' : 'disabled' }}</span>
                        <button class="btn-ghost btn-sm">{{ $p->is_enabled ? 'Disable' : 'Enable' }}</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>

<div class="grid gap-6 xl:grid-cols-[1fr_400px]">
    <section class="space-y-3">
        <h2 class="font-semibold">Campaigns</h2>
        @forelse ($campaigns as $c)
            @php $imp = (int) $c->impressions_total; $clk = (int) $c->clicks_total; @endphp
            <article x-data="disclosure" class="card p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $c->name }}</span>
                            <span class="chip !py-0.5">{{ $types[$c->type] ?? $c->type }}</span>
                            @if ($c->is_active)<span class="badge bg-ok/15 text-ok">active</span>@else<span class="badge bg-card-2 text-ink-3">paused</span>@endif
                            @if ($c->ends_at && $c->ends_at->isPast())<span class="badge bg-warn/15 text-warn">ended</span>@endif
                            @if ($c->starts_at && $c->starts_at->isFuture())<span class="badge bg-warn/15 text-warn">scheduled</span>@endif
                        </div>
                        <div class="mt-1 text-xs text-ink-3">
                            {{ $c->placement?->name ?? 'No placement' }} · priority {{ $c->priority }}
                            @if ($c->game) · {{ $c->game->tr('title', 'en') }}@endif
                            @if ($c->target_url) · <span class="font-mono">{{ \Illuminate\Support\Str::limit($c->target_url, 50) }}</span>@endif
                            @if ($c->starts_at || $c->ends_at) · {{ $c->starts_at?->format('Y-m-d') ?? '…' }} → {{ $c->ends_at?->format('Y-m-d') ?? '…' }}@endif
                        </div>
                    </div>
                    <dl class="flex gap-4 text-right text-xs">
                        <div><dt class="text-ink-3">Impr.</dt><dd class="font-semibold tabular-nums">{{ number_format($imp) }}</dd></div>
                        <div><dt class="text-ink-3">Clicks</dt><dd class="font-semibold tabular-nums">{{ number_format($clk) }}</dd></div>
                        <div><dt class="text-ink-3">CTR</dt><dd class="font-semibold tabular-nums">{{ $ctr($imp, $clk) }}</dd></div>
                    </dl>
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn-ghost btn-sm" @click="toggle" :aria-expanded="open"><x-icon name="edit" class="size-4"/>Edit</button>
                        <form method="post" action="{{ route('admin.ads.destroy', $c) }}" x-data="confirmForm" data-confirm="Delete this campaign and its statistics?" @submit="confirmSubmit">
                            @csrf @method('DELETE')
                            <button class="btn-danger btn-sm" title="Delete"><x-icon name="trash" class="size-4"/></button>
                        </form>
                    </div>
                </div>
                <div x-show="open" x-cloak class="mt-4 border-t border-line pt-4">
                    @include('admin.ads._form', ['campaign' => $c, 'fresh' => false])
                </div>
            </article>
        @empty
            <div class="card p-8 text-center text-sm text-ink-3">No campaigns yet.</div>
        @endforelse
    </section>

    <aside>
        <div class="card p-5">
            <h2 class="mb-4 font-semibold">New campaign</h2>
            @include('admin.ads._form', ['campaign' => new \App\Models\AdCampaign(), 'fresh' => true])
        </div>
    </aside>
</div>
@endsection
