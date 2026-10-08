@extends('layouts.app')

@push('head')
    @vite('resources/js/room.js')
@endpush

@section('content')
<div class="container-page max-w-3xl pt-8"
     x-data="room"
     data-state-url="{{ route('api.rooms.state', $room) }}"
     data-join-url="{{ route('api.rooms.join', $room) }}"
     data-ready-url="{{ route('api.rooms.ready', $room) }}"
     data-move-url="{{ route('api.rooms.move', $room) }}"
     data-rematch-url="{{ route('api.rooms.rematch', $room) }}"
     data-game="{{ $room->game }}"
     data-share-url="{{ url()->current() }}"
     data-copied="{{ __('Link copied!') }}"
     data-t-your-turn="{{ __('Your turn') }}"
     data-t-their-turn="{{ __('Waiting for your friend…') }}"
     data-t-you-won="{{ __('You won!') }}"
     data-t-you-lost="{{ __('Your friend won') }}"
     data-t-draw="{{ __('Draw') }}"
     data-t-spectating="{{ __('Watching') }}"
     data-t-online="{{ __('online') }}"
     data-t-away="{{ __('away') }}"
     data-t-ready="{{ __('ready') }}"
     data-t-expired="{{ __('This room has expired.') }}"
     data-t-cell="{{ __('Cell :n') }}"
     data-t-column="{{ __('Column :n') }}"
     data-copy-prompt="{{ __('Copy this link') }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs uppercase tracking-widest text-ink-3">{{ __('Room') }} <span class="font-mono text-ink-2">{{ $room->code }}</span></p>
            <h1 class="text-2xl font-black">{{ $room->game === 'connect4' ? __('Four in a Row') : __('Tic-Tac-Toe') }}</h1>
        </div>
        <button type="button" class="btn-ghost" @click="copyLink"><x-icon name="link" class="size-4"/>{{ __('Copy invite link') }}</button>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-3">
        <div class="card flex items-center gap-3 p-3" :class="{ 'ring-2 ring-brand': isTurn(0) }">
            <span class="size-4 rounded-full bg-brand-2"></span>
            <div class="min-w-0 flex-1"><div class="text-sm font-semibold">{{ __('Host') }} <span x-show="you === 0" class="text-ink-3">({{ __('you') }})</span></div>
                <div class="text-xs text-ink-3"><span x-text="hostStatus"></span></div></div>
            <span class="font-display text-2xl font-black" x-text="score0"></span>
        </div>
        <div class="card flex items-center gap-3 p-3" :class="{ 'ring-2 ring-brand': isTurn(1) }">
            <span class="size-4 rounded-full bg-brand-3"></span>
            <div class="min-w-0 flex-1"><div class="text-sm font-semibold">{{ __('Guest') }} <span x-show="you === 1" class="text-ink-3">({{ __('you') }})</span></div>
                <div class="text-xs text-ink-3"><span x-text="guestStatus"></span></div></div>
            <span class="font-display text-2xl font-black" x-text="score1"></span>
        </div>
    </div>

    <div class="card mt-4 p-4 sm:p-6">
        <p class="mb-4 text-center text-sm font-semibold" x-text="message" data-testid="room-message"></p>

        <template x-if="canJoin">
            <div class="text-center"><button type="button" class="btn-primary" @click="join" data-testid="join-room">{{ __('Join this game') }}</button></div>
        </template>
        <template x-if="showReady">
            <div class="text-center"><button type="button" class="btn-primary" @click="ready" data-testid="ready">{{ __("I'm ready") }}</button></div>
        </template>
        <template x-if="waitingForGuest">
            <div class="text-center text-sm text-ink-2">{{ __('Send the invite link to a friend. The game starts when both players are ready.') }}</div>
        </template>

        {{-- Tic-tac-toe board --}}
        <div x-show="hasBoard && game === 'tictactoe'" class="mx-auto grid w-full max-w-xs grid-cols-3 gap-2">
            <template x-for="i in cells9" :key="i">
                <button type="button" class="flex aspect-square items-center justify-center rounded-xl border border-line bg-bg-2 text-5xl font-black transition hover:bg-card-2"
                        :class="cellClass(i)" @click="playCell(i)" :data-testid="'cell-' + i" :aria-label="cellLabel(i)">
                    <span x-text="cellMark(i)"></span>
                </button>
            </template>
        </div>

        {{-- Connect four board --}}
        <div x-show="hasBoard && game === 'connect4'" class="mx-auto w-full max-w-md">
            <div class="grid grid-cols-7 gap-1.5 rounded-2xl bg-brand/25 p-2">
                <template x-for="i in cells42" :key="i">
                    <button type="button" class="aspect-square rounded-full transition" :class="discClass(i)" @click="playCol(i)" :data-testid="'disc-' + i" :aria-label="colLabel(i)"></button>
                </template>
            </div>
        </div>

        <template x-if="isFinished && you !== null">
            <div class="mt-5 text-center"><button type="button" class="btn-primary" @click="rematch" data-testid="rematch">{{ __('Play again') }}</button></div>
        </template>
        <p x-cloak x-show="error" class="mt-3 text-center text-sm text-bad" x-text="error"></p>
    </div>
</div>
@endsection
