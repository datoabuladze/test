@extends('layouts.app')

@section('content')
<div class="container-page pt-8">
    <div class="card relative overflow-hidden p-6 sm:p-8">
        <div class="absolute -right-20 -top-20 size-72 rounded-full bg-brand/20 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center">
            <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-brand to-brand-3 text-3xl font-black text-white">
                @if ($profile->avatarUrl())<img src="{{ $profile->avatarUrl() }}" alt="" class="size-full object-cover">@else{{ $profile->initials() }}@endif
            </div>
            <div class="flex-1">
                <h1 class="text-2xl font-black">{{ $profile->nickname }}</h1>
                <p class="text-sm text-ink-2">{{ __('Level :n', ['n' => $profile->level]) }} · {{ number_format($profile->xp) }} XP</p>
                <div class="mt-2 h-2 max-w-xs overflow-hidden rounded-full bg-line"><div class="h-full rounded-full bg-gradient-to-r from-brand-2 to-brand" style="width: {{ round($profile->levelProgress() * 100) }}%"></div></div>
            </div>
            <dl class="grid grid-cols-4 gap-4 text-center">
                @foreach (['plays' => __('Plays'), 'games' => __('Games'), 'favorites' => __('Favorites'), 'achievements' => __('Badges')] as $k => $label)
                    <div><dt class="text-xs text-ink-3">{{ $label }}</dt><dd class="font-display text-xl font-black">{{ number_format($stats[$k]) }}</dd></div>
                @endforeach
            </dl>
        </div>
    </div>

    @if ($achievements->isNotEmpty())
        <section class="mt-8">
            <h2 class="mb-3 text-lg font-bold">{{ __('Badges') }}</h2>
            <div class="flex flex-wrap gap-3">
                @foreach ($achievements as $ua)
                    <div class="card flex items-center gap-3 px-3 py-2" title="{{ $ua->achievement->tr('description') }}">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-amber-400/15 text-amber-300"><x-icon :name="$ua->achievement->icon" class="size-5"/></span>
                        <div><div class="text-sm font-semibold">{{ $ua->achievement->tr('name') }}</div><div class="text-[11px] text-ink-3">{{ $ua->unlocked_at->translatedFormat('j M Y') }}</div></div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-8">
        <h2 class="mb-3 text-lg font-bold">{{ __('Favorite games') }}</h2>
        @if ($favorites->isEmpty())
            <p class="text-sm text-ink-3">{{ __('No favorites yet.') }}</p>
        @else
            <x-game-grid :games="$favorites"/>
        @endif
    </section>
</div>
@endsection
