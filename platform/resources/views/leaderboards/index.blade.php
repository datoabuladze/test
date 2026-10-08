@extends('layouts.app')

@section('content')
<div class="container-page pt-6">
    <h1 class="mb-6 text-3xl font-black">{{ __('Leaderboards') }}</h1>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <section class="card p-5">
            <h2 class="mb-4 flex items-center gap-2 text-lg font-bold"><x-icon name="trophy" class="size-5 text-amber-300"/>{{ __('Top players by XP') }}</h2>
            @if ($players->isEmpty())
                <p class="text-sm text-ink-3">{{ __('No ranked players yet. Sign up and start playing to claim the top spot!') }}</p>
            @else
                <ol class="space-y-1">
                    @foreach ($players as $i => $p)
                        <li class="flex items-center gap-3 rounded-lg px-2 py-2 {{ $i < 3 ? 'bg-card-2' : '' }}">
                            <span class="w-6 text-center font-display font-black {{ ['text-amber-300', 'text-slate-300', 'text-orange-400'][$i] ?? 'text-ink-3' }}">{{ $i + 1 }}</span>
                            <a href="{{ route('profiles.show', $p->nickname) }}" class="flex-1 truncate font-medium hover:text-brand-2">{{ $p->nickname }}</a>
                            <span class="text-xs text-ink-3">{{ __('Lv :n', ['n' => $p->level]) }}</span>
                            <span class="w-20 text-right font-semibold">{{ number_format($p->xp) }} XP</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
        <section>
            <h2 class="mb-4 text-lg font-bold">{{ __('Game high scores') }}</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($games as $game)
                    <a href="{{ route('leaderboards.show', $game) }}" class="card flex items-center gap-3 p-2.5 hover:shadow-glow">
                        <div class="w-16 shrink-0 overflow-hidden rounded-lg"><div class="aspect-[4/3]"><x-game-thumb :game="$game"/></div></div>
                        <span class="truncate text-sm font-medium">{{ $game->tr('title') }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</div>
@endsection
