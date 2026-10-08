@extends('layouts.app')

@section('content')
<div class="container-page max-w-3xl pt-6">
    <x-breadcrumbs :items="[[__('Home'), route('home')], [__('Leaderboards'), route('leaderboards.index')], [$game->tr('title'), null]]" class="mb-3"/>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-3xl font-black">{{ __(':game leaderboard', ['game' => $game->tr('title')]) }}</h1>
        <a href="{{ $game->url() }}" class="btn-primary"><x-icon name="play" class="size-4 fill-current"/>{{ __('Play') }}</a>
    </div>
    <div class="mt-5 flex flex-wrap gap-2">
        <a class="chip {{ $verifiedOnly ? 'chip-active' : '' }}" href="{{ request()->fullUrlWithQuery(['board' => 'verified']) }}"><x-icon name="shield" class="size-3.5"/>{{ __('Verified') }}</a>
        <a class="chip {{ ! $verifiedOnly ? 'chip-active' : '' }}" href="{{ request()->fullUrlWithQuery(['board' => 'casual']) }}">{{ __('Casual') }}</a>
        <span class="mx-1 h-5 w-px self-center bg-line"></span>
        @foreach (['all' => __('All time'), 'month' => __('This month'), 'week' => __('This week'), 'day' => __('Today')] as $k => $label)
            <a class="chip {{ $period === $k ? 'chip-active' : '' }}" href="{{ request()->fullUrlWithQuery(['period' => $k]) }}">{{ $label }}</a>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-ink-3">
        {{ $verifiedOnly ? __('Verified scores were re-simulated on our server from the recorded moves.') : __('Casual scores pass plausibility checks but are not replay-verified.') }}
    </p>
    <div class="card mt-4 overflow-hidden">
        @if ($rows->isEmpty())
            <p class="p-6 text-sm text-ink-3">{{ __('No scores yet. Be the first!') }}</p>
        @else
            <table class="table">
                <thead><tr><th class="w-12">#</th><th>{{ __('Player') }}</th><th class="text-right">{{ __('Score') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="font-display font-black {{ ['text-amber-300', 'text-slate-300', 'text-orange-400'][$row['rank'] - 1] ?? 'text-ink-3' }}">{{ $row['rank'] }}</td>
                            <td><a class="hover:text-brand-2" href="{{ route('profiles.show', $row['user']->nickname) }}">{{ $row['user']->nickname }}</a></td>
                            <td class="text-right font-semibold tabular-nums">{{ number_format($row['score']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @if ($mine !== null)
        <p class="mt-3 text-sm text-ink-2">{{ __('Your best: :score', ['score' => number_format($mine)]) }}</p>
    @endif
</div>
@endsection
