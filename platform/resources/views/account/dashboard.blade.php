@extends('layouts.app')
@section('content')
<div class="container-page pt-8">
    @include('account.nav')
    @if (! $user->hasVerifiedEmail())
        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-xl border border-warn/30 bg-warn/10 px-4 py-3 text-sm">
            <x-icon name="mail" class="size-4 text-warn"/> {{ __('Please verify your email address.') }}
            <form method="post" action="{{ route('verification.send') }}" class="ml-auto">@csrf<button class="font-semibold text-warn underline">{{ __('Resend link') }}</button></form>
        </div>
    @endif
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5"><div class="text-xs text-ink-3">{{ __('Level') }}</div><div class="font-display text-3xl font-black">{{ $user->level }}</div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full bg-gradient-to-r from-brand-2 to-brand" style="width: {{ round($user->levelProgress() * 100) }}%"></div></div>
            <div class="mt-1 text-xs text-ink-3">{{ number_format($user->xp) }} / {{ number_format(\App\Models\User::xpForLevel($user->level + 1)) }} XP</div></div>
        <div class="card p-5"><div class="text-xs text-ink-3">{{ __('Daily streak') }}</div><div class="flex items-center gap-2 font-display text-3xl font-black"><x-icon name="fire" class="size-7 text-orange-400"/>{{ $streak }}</div>
            <div class="mt-1 text-xs text-ink-3">{{ __('Play every day to keep it going. +:xp XP for your first game each day.', ['xp' => \App\Services\Gamification::XP_DAILY_FIRST_PLAY]) }}</div></div>
        <div class="card p-5"><div class="text-xs text-ink-3">{{ __('Public profile') }}</div>
            <a href="{{ route('profiles.show', $user->nickname) }}" class="mt-1 block truncate font-semibold text-brand-2 hover:underline">{{ $user->nickname }}</a>
            <div class="mt-1 text-xs text-ink-3">{{ $user->profile_public ? __('Visible to everyone') : __('Only visible to you') }}</div></div>
    </div>
    <x-game-rail :title="__('Continue playing')" :games="$recent" icon="history" class="mt-6"/>
    <x-game-rail :title="__('Favorites')" :games="$favorites" icon="heart" :href="route('account.favorites')"/>
    @if ($recent->isEmpty() && $favorites->isEmpty())
        <x-empty-state :title="__('Your adventure starts here')" class="mt-6">{{ __('Play a game or tap the heart on one you like, and it will show up here.') }}</x-empty-state>
    @endif
</div>
@endsection
