@extends('layouts.app')
@section('content')
<div class="container-page pt-8">
    @include('account.nav')
    @foreach ($rows->groupBy(fn ($r) => $r['achievement']->period) as $period => $group)
        <h2 class="mb-3 mt-6 text-lg font-bold">{{ ['lifetime' => __('Achievements'), 'daily' => __('Daily challenges'), 'weekly' => __('Weekly challenges'), 'monthly' => __('Monthly challenges')][$period] ?? $period }}</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($group as $row)
                @php $a = $row['achievement']; $pct = $a->threshold ? min(100, round($row['progress'] / $a->threshold * 100)) : 0; @endphp
                <div class="card flex gap-4 p-4 {{ $row['done'] ? 'border-amber-400/40' : '' }}">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $row['done'] ? 'bg-amber-400/20 text-amber-300' : 'bg-card-2 text-ink-3' }}"><x-icon :name="$a->icon" class="size-6"/></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2"><h3 class="truncate font-semibold">{{ $a->tr('name') }}</h3><span class="shrink-0 text-xs text-brand-2">+{{ $a->xp_reward }} XP</span></div>
                        <p class="text-xs text-ink-3">{{ $a->tr('description') }}</p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full {{ $row['done'] ? 'bg-amber-300' : 'bg-brand' }}" style="width: {{ $pct }}%"></div></div>
                        <div class="mt-1 text-[11px] text-ink-3">{{ number_format($row['progress']) }} / {{ number_format($a->threshold) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endsection
